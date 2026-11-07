<?php

namespace Tests\Unit;

use Anthropic\Client;
use App\Support\Ai\AiUnavailable;
use App\Support\Ai\ClaudeConnector;
use App\Support\Ai\ToolFailed;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The tool loop against the real SDK, with the network swapped for a stub.
 *
 * Feature tests fake the whole connector, so this is the one place that checks
 * what actually goes over the wire: that a tool round echoes the assistant turn
 * untouched (thinking blocks and all), that results go back keyed to their
 * call, and that caching and the refusal fallback are switched on.
 */
class ClaudeConnectorTest extends TestCase
{
    /** @var list<RequestInterface> */
    private array $requests = [];

    /** @param list<array<string, mixed>> $replies API response bodies, in order */
    private function connector(array $replies, int $maxToolRounds = 4): ClaudeConnector
    {
        $transport = new class($replies, $this->requests) implements ClientInterface
        {
            public function __construct(private array $replies, private array &$requests)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;
                $body = array_shift($this->replies) ?? throw new \RuntimeException('No more scripted replies.');

                return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
            }
        };

        return new ClaudeConnector(
            apiKey: 'test-key',
            model: 'claude-opus-5',
            maxTokens: 1024,
            timeout: 5,
            chatModel: 'claude-opus-5-5',
            chatEffort: 'low',
            chatMaxTokens: 4096,
            maxToolRounds: $maxToolRounds,
            client: new Client(apiKey: 'test-key', requestOptions: ['transporter' => $transport, 'maxRetries' => 0]),
        );
    }

    private static function message(string $stopReason, array $content): array
    {
        return [
            'id' => 'msg_'.uniqid(), 'type' => 'message', 'role' => 'assistant',
            'model' => 'claude-opus-5-5', 'stop_reason' => $stopReason, 'stop_sequence' => null,
            'content' => $content,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ];
    }

    private function body(int $i): array
    {
        return json_decode((string) $this->requests[$i]->getBody(), true);
    }

    public function test_a_tool_round_trip_goes_over_the_wire_correctly(): void
    {
        $thinking = ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-abc'];
        $call = ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'search_properties', 'input' => ['area' => 'DHA']];

        $connector = $this->connector([
            self::message('tool_use', [$thinking, $call]),
            self::message('end_turn', [['type' => 'text', 'text' => 'Found one in DHA.']]),
        ]);

        $seen = [];
        $reply = $connector->converse(
            'SYSTEM',
            [['role' => 'user', 'content' => 'Houses in DHA?']],
            [['name' => 'search_properties', 'description' => 'Search.', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]]],
            function (string $name, array $input) use (&$seen) {
                $seen[] = [$name, $input];

                return '{"total_matches":1}';
            },
        );

        $this->assertSame('Found one in DHA.', $reply);
        $this->assertSame([['search_properties', ['area' => 'DHA']]], $seen);
        $this->assertCount(2, $this->requests);

        $first = $this->body(0);
        $this->assertSame('claude-opus-5-5', $first['model']);
        $this->assertSame(['type' => 'ephemeral'], $first['cache_control']);
        $this->assertSame('default', $first['fallbacks']);
        $this->assertSame('low', $first['output_config']['effort']);
        $this->assertSame('SYSTEM', $first['system']);
        $this->assertSame('search_properties', $first['tools'][0]['name']);
        $this->assertArrayHasKey('input_schema', $first['tools'][0]);
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $this->requests[0]->getHeaderLine('anthropic-beta'));

        // Round two: the assistant turn echoed exactly, then the result keyed to its call.
        $second = $this->body(1);
        $this->assertCount(3, $second['messages']);
        $this->assertSame('assistant', $second['messages'][1]['role']);
        $this->assertSame('sig-abc', $second['messages'][1]['content'][0]['signature']);
        $this->assertSame('toolu_1', $second['messages'][1]['content'][1]['id']);
        $this->assertSame([
            'role' => 'user',
            'content' => [['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => '{"total_matches":1}']],
        ], $second['messages'][2]);
    }

    public function test_a_failed_tool_is_returned_as_an_error_result(): void
    {
        $connector = $this->connector([
            self::message('tool_use', [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_property', 'input' => ['slug' => 'nope']]]),
            self::message('end_turn', [['type' => 'text', 'text' => 'I could not find that one.']]),
        ]);

        $connector->converse('S', [['role' => 'user', 'content' => 'Hi']], [], fn () => throw new ToolFailed('No such listing.'));

        $result = $this->body(1)['messages'][2]['content'][0];
        $this->assertSame('No such listing.', $result['content']);
        $this->assertTrue($result['is_error']);
    }

    public function test_a_refusal_is_an_outage_not_an_answer(): void
    {
        $connector = $this->connector([self::message('refusal', [])]);

        $this->expectException(AiUnavailable::class);
        $connector->converse('S', [['role' => 'user', 'content' => 'Hi']], [], fn () => '');
    }

    public function test_a_model_that_never_stops_calling_tools_is_cut_off(): void
    {
        $loop = self::message('tool_use', [['type' => 'tool_use', 'id' => 'toolu_x', 'name' => 'search_properties', 'input' => []]]);
        $connector = $this->connector([$loop, $loop, $loop], maxToolRounds: 2);

        try {
            $connector->converse('S', [['role' => 'user', 'content' => 'Hi']], [], fn () => '[]');
            $this->fail('Expected the loop to be cut off.');
        } catch (AiUnavailable) {
            $this->assertCount(3, $this->requests);
        }
    }

    public function test_complete_still_makes_a_single_plain_call(): void
    {
        $connector = $this->connector([self::message('end_turn', [['type' => 'text', 'text' => 'Draft.']])]);

        $this->assertSame('Draft.', $connector->complete('S', 'Prompt'));
        $this->assertSame('claude-opus-5', $this->body(0)['model']);
    }
}
