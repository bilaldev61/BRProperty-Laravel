<?php

namespace Tests\Feature;

use App\Support\Ai\AiConnector;
use App\Support\Ai\AiUnavailable;
use App\Support\Ai\ClaudeConnector;
use App\Support\Ai\NullConnector;
use App\Support\Ai\OpenRouterConnector;
use App\Support\Ai\ToolFailed;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The OpenRouter connector against a faked HTTP layer: what goes over the wire
 * on each tool round, and how failures surface.
 */
class OpenRouterConnectorTest extends TestCase
{
    private const URL = 'https://openrouter.ai/api/v1/chat/completions';

    private function connector(int $maxToolRounds = 4): OpenRouterConnector
    {
        return new OpenRouterConnector(
            apiKey: 'sk-or-test',
            model: 'openai/gpt-4o',
            chatModel: 'openai/gpt-4o-mini',
            maxTokens: 1024,
            chatMaxTokens: 2048,
            timeout: 5,
            maxToolRounds: $maxToolRounds,
        );
    }

    private static function reply(array $message, string $finish = 'stop'): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', ...$message], 'finish_reason' => $finish]]];
    }

    private static function toolCall(string $id, string $name, string $arguments): array
    {
        return ['id' => $id, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => $arguments]];
    }

    private static function tools(): array
    {
        return [['name' => 'search_properties', 'description' => 'Search.', 'inputSchema' => ['type' => 'object', 'properties' => ['area' => ['type' => 'string']]]]];
    }

    public function test_a_tool_round_trip_goes_over_the_wire_correctly(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(self::reply(['content' => null, 'tool_calls' => [self::toolCall('call_1', 'search_properties', '{"area":"DHA"}')]], 'tool_calls'))
            ->push(self::reply(['content' => 'Found one in DHA.'])),
        ]);

        $seen = [];
        $reply = $this->connector()->converse('SYSTEM', [['role' => 'user', 'content' => 'Houses in DHA?']], self::tools(),
            function (string $name, array $input) use (&$seen) {
                $seen[] = [$name, $input];

                return '{"total_matches":1}';
            });

        $this->assertSame('Found one in DHA.', $reply);
        $this->assertSame([['search_properties', ['area' => 'DHA']]], $seen);

        $requests = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertCount(2, $requests);

        /** @var Request $first */
        $first = $requests[0];
        $this->assertSame('Bearer sk-or-test', $first->header('Authorization')[0]);
        $this->assertSame('openai/gpt-4o-mini', $first['model']);
        $this->assertSame(['role' => 'system', 'content' => 'SYSTEM'], $first['messages'][0]);
        $this->assertSame('function', $first['tools'][0]['type']);
        $this->assertSame('search_properties', $first['tools'][0]['function']['name']);
        $this->assertSame('object', $first['tools'][0]['function']['parameters']['type']);

        // Round two: the assistant turn echoed, then the result keyed to its call.
        $second = $requests[1]['messages'];
        $this->assertSame('call_1', $second[2]['tool_calls'][0]['id']);
        $this->assertSame(['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '{"total_matches":1}'], $second[3]);
    }

    public function test_a_failed_tool_or_broken_arguments_are_reported_to_the_model(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push(self::reply(['content' => null, 'tool_calls' => [
                self::toolCall('call_1', 'get_property', '{"slug":"nope"}'),
                self::toolCall('call_2', 'get_property', '{not json'),
            ]], 'tool_calls'))
            ->push(self::reply(['content' => 'Sorry, I could not find it.'])),
        ]);

        $this->connector()->converse('S', [['role' => 'user', 'content' => 'Hi']], self::tools(),
            fn () => throw new ToolFailed('No such listing.'));

        $messages = Http::recorded()[1][0]['messages'];
        $this->assertSame('Error: No such listing.', $messages[3]['content']);
        $this->assertStringStartsWith('Error: the arguments were not valid JSON', $messages[4]['content']);
    }

    public function test_complete_makes_one_plain_call_on_the_draft_model(): void
    {
        Http::fake([self::URL => Http::response(self::reply(['content' => 'Draft.']))]);

        $this->assertSame('Draft.', $this->connector()->complete('S', 'Prompt'));

        Http::assertSent(fn (Request $r) => $r['model'] === 'openai/gpt-4o'
            && $r['messages'][1] === ['role' => 'user', 'content' => 'Prompt']
            && ! isset($r['tools']));
    }

    public function test_an_http_error_is_an_outage(): void
    {
        Http::fake([self::URL => Http::response(['error' => ['message' => 'Insufficient credits']], 402)]);

        $this->expectException(AiUnavailable::class);
        $this->expectExceptionMessage('Insufficient credits');
        $this->connector()->complete('S', 'P');
    }

    public function test_an_error_inside_a_200_is_an_outage(): void
    {
        Http::fake([self::URL => Http::response(['error' => ['message' => 'Provider returned error']])]);

        $this->expectException(AiUnavailable::class);
        $this->connector()->complete('S', 'P');
    }

    public function test_an_empty_reply_is_an_outage(): void
    {
        Http::fake([self::URL => Http::response(self::reply(['content' => '']))]);

        $this->expectException(AiUnavailable::class);
        $this->connector()->converse('S', [['role' => 'user', 'content' => 'Hi']], [], fn () => '');
    }

    public function test_a_model_that_never_stops_calling_tools_is_cut_off(): void
    {
        Http::fake([self::URL => Http::response(self::reply(['content' => null, 'tool_calls' => [
            self::toolCall('call_x', 'search_properties', '{}'),
        ]], 'tool_calls'))]);

        try {
            $this->connector(maxToolRounds: 2)->converse('S', [['role' => 'user', 'content' => 'Hi']], self::tools(), fn () => '[]');
            $this->fail('Expected the loop to be cut off.');
        } catch (AiUnavailable) {
            Http::assertSentCount(3);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Which connector the app picks
    |--------------------------------------------------------------------------
    */

    private function resolve(): AiConnector
    {
        $this->app->forgetInstance(AiConnector::class);

        return $this->app->make(AiConnector::class);
    }

    public function test_the_driver_picks_the_connector(): void
    {
        config(['ai.driver' => 'openrouter', 'ai.openrouter.key' => 'sk-or-x', 'ai.key' => null]);
        $this->assertInstanceOf(OpenRouterConnector::class, $this->resolve());

        config(['ai.driver' => 'anthropic', 'ai.key' => 'sk-ant-x']);
        $this->assertInstanceOf(ClaudeConnector::class, $this->resolve());

        config(['ai.driver' => 'openrouter', 'ai.openrouter.key' => null]);
        $this->assertInstanceOf(NullConnector::class, $this->resolve());
    }
}
