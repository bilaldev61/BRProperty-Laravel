<?php

namespace App\Support\Ai;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Talks to OpenRouter: one key, models from many vendors, behind an
 * OpenAI-style chat completions API.
 *
 * Same contract as ClaudeConnector - every failure leaves as AiUnavailable, and
 * the tool loop stays in here - so the services above cannot tell which
 * provider answered.
 */
class OpenRouterConnector implements AiConnector
{
    private int $tokens = 0;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $chatModel,
        private readonly int $maxTokens,
        private readonly int $chatMaxTokens,
        private readonly int $timeout,
        private readonly int $maxToolRounds = 4,
        private readonly string $baseUrl = 'https://openrouter.ai/api/v1',
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function lastTokens(): int
    {
        return $this->tokens;
    }

    public function complete(string $system, string $prompt): string
    {
        if (! $this->isConfigured()) {
            throw AiUnavailable::notConfigured();
        }

        $this->tokens = 0;

        $message = $this->call([
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages'   => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return $this->textOf($message) ?? throw AiUnavailable::emptyResponse();
    }

    public function converse(string $system, array $messages, array $tools, Closure $runTool): string
    {
        if (! $this->isConfigured()) {
            throw AiUnavailable::notConfigured();
        }

        $this->tokens = 0;

        $messages = [['role' => 'system', 'content' => $system], ...$messages];
        $tools = array_map(fn (array $tool) => [
            'type'     => 'function',
            'function' => [
                'name'        => $tool['name'],
                'description' => $tool['description'],
                'parameters'  => $tool['inputSchema'],
            ],
        ], $tools);

        for ($round = 0; $round <= $this->maxToolRounds; $round++) {
            $message = $this->call(array_filter([
                'model'      => $this->chatModel,
                'max_tokens' => $this->chatMaxTokens,
                'messages'   => $messages,
                'tools'      => $tools ?: null,
            ]));

            $calls = $message['tool_calls'] ?? [];

            if ($calls === []) {
                return $this->textOf($message) ?? throw AiUnavailable::emptyResponse();
            }

            // The assistant turn goes back as it came, then one `tool` message
            // per call, each keyed to the call it answers.
            $messages[] = $message;

            foreach ($calls as $call) {
                $messages[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $call['id'],
                    'content'      => $this->runTool($runTool, $call['function'] ?? []),
                ];
            }
        }

        throw AiUnavailable::callFailed('the model kept calling tools without answering');
    }

    private function runTool(Closure $runTool, array $function): string
    {
        // Arguments arrive as a JSON string the model wrote - it can be broken.
        $input = json_decode($function['arguments'] ?? '{}', true);

        if (! is_array($input)) {
            return 'Error: the arguments were not valid JSON. Call the tool again with a JSON object.';
        }

        try {
            return $runTool((string) ($function['name'] ?? ''), $input);
        } catch (ToolFailed $e) {
            // No error flag in this API: say so in the result itself.
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Make one request and return the reply message, folding every way it can
     * fail - network, HTTP status, an error in a 200 body - into AiUnavailable.
     *
     * @return array<string, mixed>
     */
    private function call(array $payload): array
    {
        try {
            $response = $this->http()->post('/chat/completions', $payload);
        } catch (Throwable $e) {
            throw AiUnavailable::callFailed($e->getMessage());
        }

        if ($response->failed() || $response->json('error')) {
            throw AiUnavailable::callFailed($this->reason($response));
        }

        $this->tokens += (int) $response->json('usage.total_tokens', 0);

        $choice = $response->json('choices.0');

        if (! is_array($choice) || ! is_array($choice['message'] ?? null)) {
            throw AiUnavailable::emptyResponse();
        }

        if (($choice['finish_reason'] ?? null) === 'content_filter') {
            throw AiUnavailable::callFailed('the model declined this request');
        }

        return $choice['message'];
    }

    private function reason(Response $response): string
    {
        return $response->json('error.message') ?? "HTTP {$response->status()}";
    }

    private function textOf(array $message): ?string
    {
        $content = $message['content'] ?? null;

        return is_string($content) && filled(trim($content)) ? $content : null;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout)
            // Optional OpenRouter headers: they label the traffic in its dashboard.
            ->withHeaders([
                'HTTP-Referer' => (string) config('app.url'),
                'X-Title'      => (string) config('agent.agency'),
            ]);
    }
}
