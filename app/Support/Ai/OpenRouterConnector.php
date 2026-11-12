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

    /** @var list<array<string, mixed>> one entry per request of the last call */
    private array $requests = [];

    /** @var array<string, int> what the single request inside call() used */
    private array $lastUsage = [];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $chatModel,
        private readonly int $maxTokens,
        private readonly int $chatMaxTokens,
        private readonly int $timeout,
        private readonly int $maxToolRounds = 4,
        private readonly string $chatEffort = 'low',
        private readonly string $baseUrl = 'https://openrouter.ai/api/v1',
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function lastTokens(): int
    {
        return $this->tokens;
    }

    public function lastRequests(): array
    {
        return $this->requests;
    }

    public function complete(string $system, string $prompt): string
    {
        if (! $this->isConfigured()) {
            throw AiUnavailable::notConfigured();
        }

        $this->tokens = 0;
        $this->requests = [];

        $started = microtime(true);

        $message = $this->call([
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages'   => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $this->record(0, $this->model, $started, []);

        return $this->textOf($message) ?? throw AiUnavailable::emptyResponse();
    }

    public function converse(string $system, array $messages, array $tools, Closure $runTool, ?string $session = null): string
    {
        if (! $this->isConfigured()) {
            throw AiUnavailable::notConfigured();
        }

        $this->tokens = 0;
        $this->requests = [];

        $messages = [['role' => 'system', 'content' => $system], ...$messages];
        $tools = array_map(fn(array $tool) => [
            'type'     => 'function',
            'function' => [
                'name'        => $tool['name'],
                'description' => $tool['description'],
                'parameters'  => $tool['inputSchema'],
            ],
        ], $tools);

        for ($round = 0; $round <= $this->maxToolRounds; $round++) {
            $started = microtime(true);

            $message = $this->call(array_filter([
                'model'         => $this->chatModel,
                'max_tokens'    => $this->chatMaxTokens,
                'messages'      => $messages,
                'tools'         => $tools ?: null,
                'session_id'    => $session,
                'cache_control' => $this->cacheControl(),
                // Reasoning models think by default, and every hidden thought
                // is billed reply tokens on every round. Effort is the brake.
                'reasoning_effort' => $this->chatEffort ?: null,
            ]));

            $calls = $message['tool_calls'] ?? [];

            $this->record($round, $this->chatModel, $started, array_map(
                fn(array $call) => (string) ($call['function']['name'] ?? ''),
                $calls,
            ));

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

    /**
     * A top-level breakpoint, for the one provider family that will not cache
     * without being told. OpenAI, DeepSeek and Gemini cache the prefix on their
     * own, and an explicit block is ignored there.
     */
    private function cacheControl(): ?array
    {
        return str_contains($this->chatModel, 'anthropic/') || str_contains($this->chatModel, 'claude')
            ? ['type' => 'ephemeral']
            : null;
    }

    /** @param list<string> $tools */
    private function record(int $round, string $model, float $started, array $tools): void
    {
        $this->requests[] = [
            'round'              => $round,
            'model'              => $model,
            'prompt_tokens'      => $this->lastUsage['prompt_tokens'] ?? 0,
            'completion_tokens'  => $this->lastUsage['completion_tokens'] ?? 0,
            'cached_tokens'      => $this->lastUsage['cached_tokens'] ?? 0,
            'cache_write_tokens' => $this->lastUsage['cache_write_tokens'] ?? 0,
            'latency_ms'         => (int) round((microtime(true) - $started) * 1000),
            'tools'              => array_values(array_filter($tools)),
        ];
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
            return 'Error: ' . $e->getMessage();
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

        $this->readUsage($response);

        $choice = $response->json('choices.0');

        if (! is_array($choice) || ! is_array($choice['message'] ?? null)) {
            throw AiUnavailable::emptyResponse();
        }

        if (($choice['finish_reason'] ?? null) === 'content_filter') {
            throw AiUnavailable::callFailed('the model declined this request');
        }

        return $choice['message'];
    }

    /**
     * Cached input is billed at a fraction of the price, so the split matters:
     * `cached_tokens` is the only evidence that the prefix is being reused.
     */
    private function readUsage(Response $response): void
    {
        $prompt     = (int) $response->json('usage.prompt_tokens', 0);
        $completion = (int) $response->json('usage.completion_tokens', 0);
        $cached     = (int) $response->json('usage.prompt_tokens_details.cached_tokens', 0);
        $written    = (int) $response->json('usage.prompt_tokens_details.cache_write_tokens', 0);

        $this->tokens += (int) $response->json('usage.total_tokens', $prompt + $completion);

        $this->lastUsage = [
            'prompt_tokens'      => $prompt,
            'completion_tokens'  => $completion,
            'cached_tokens'      => $cached,
            'cache_write_tokens' => $written,
        ];
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
