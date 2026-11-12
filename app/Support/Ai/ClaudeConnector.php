<?php

namespace App\Support\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use Closure;
use Throwable;

/**
 * The real connector. Every failure - no credentials, network, rate limit,
 * refusal, empty body - leaves as AiUnavailable, so callers have one thing to
 * catch and the agent always falls back to writing it themselves.
 */
class ClaudeConnector implements AiConnector
{
    /** Opt-in header for the server-side `fallbacks: 'default'` retry below. */
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private int $tokens = 0;

    /** @var list<array<string, mixed>> one entry per request of the last call */
    private array $requests = [];

    /** @var array<string, int> what the single request inside call() used */
    private array $lastUsage = [];

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly int $timeout,
        private readonly string $chatModel = 'claude-opus-5-5',
        private readonly string $chatEffort = 'low',
        private readonly int $chatMaxTokens = 4096,
        private readonly int $maxToolRounds = 4,
        private ?Client $client = null,
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

        // `thinking` is deliberately omitted: Opus 5 runs adaptive thinking by
        // default, and maxTokens is the real ceiling on a short reply.
        $started = microtime(true);

        $message = $this->call(fn() => $this->client()->messages->create(
            maxTokens: $this->maxTokens,
            messages: [['role' => 'user', 'content' => $prompt]],
            model: $this->model,
            system: $system,
            requestOptions: ['timeout' => (float) $this->timeout],
        ));

        $this->record(0, $this->model, $started);

        return $this->textOf($message) ?? throw AiUnavailable::emptyResponse();
    }

    public function converse(string $system, array $messages, array $tools, Closure $runTool, ?string $session = null): string
    {
        if (! $this->isConfigured()) {
            throw AiUnavailable::notConfigured();
        }

        $this->tokens = 0;
        $this->requests = [];

        for ($round = 0; $round <= $this->maxToolRounds; $round++) {
            $started = microtime(true);

            $message = $this->call(fn() => $this->client()->beta->messages->create(
                maxTokens: $this->chatMaxTokens,
                messages: $messages,
                model: $this->chatModel,
                // Caches the system prompt, tools and history up to the newest
                // block, so each turn - and each tool round inside a turn - only
                // pays full price for what is new.
                cacheControl: ['type' => 'ephemeral'],
                // If the model declines a harmless property question on policy
                // grounds, the API retries it on a fallback model in the same
                // call rather than leaving the visitor with nothing.
                fallbacks: 'default',
                // 'none' is an OpenRouter-only value; Anthropic has no such
                // effort, so it keeps its default rather than a 400.
                outputConfig: in_array($this->chatEffort, ['low', 'medium', 'high'], true)
                    ? ['effort' => $this->chatEffort]
                    : null,
                system: $system,
                tools: $tools,
                betas: [self::FALLBACK_BETA],
                requestOptions: ['timeout' => (float) $this->timeout],
            ));

            $names = $message->stopReason === 'tool_use'
                ? collect($message->content)
                ->filter(fn($block) => ($block->type ?? null) === 'tool_use')
                ->map(fn($block) => (string) $block->name)
                ->values()
                ->all()
                : [];

            $this->record($round, $this->chatModel, $started, $names);

            if ($message->stopReason !== 'tool_use') {
                return $this->textOf($message) ?? throw AiUnavailable::emptyResponse();
            }

            $results = [];
            foreach ($message->content as $block) {
                if ($block->type === 'tool_use') {
                    $results[] = $this->runTool($runTool, $block->id, $block->name, $block->input);
                }
            }

            // The assistant turn goes back exactly as it came - thinking blocks
            // included - or the API rejects the next round. Every result goes
            // back in a single user turn.
            $messages[] = ['role' => 'assistant', 'content' => $message->content];
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        throw AiUnavailable::callFailed('the model kept calling tools without answering');
    }

    /** @param list<string> $tools */
    private function record(int $round, string $model, float $started, array $tools = []): void
    {
        $this->requests[] = [
            'round'              => $round,
            'model'              => $model,
            'prompt_tokens'      => $this->lastUsage['prompt_tokens'] ?? 0,
            'completion_tokens'  => $this->lastUsage['completion_tokens'] ?? 0,
            'cached_tokens'      => $this->lastUsage['cached_tokens'] ?? 0,
            'cache_write_tokens' => $this->lastUsage['cache_write_tokens'] ?? 0,
            'latency_ms'         => (int) round((microtime(true) - $started) * 1000),
            'tools'              => $tools,
        ];
    }

    /** @return array<string, mixed> one tool_result block */
    private function runTool(Closure $runTool, string $id, string $name, array $input): array
    {
        try {
            return ['type' => 'tool_result', 'toolUseID' => $id, 'content' => $runTool($name, $input)];
        } catch (ToolFailed $e) {
            return ['type' => 'tool_result', 'toolUseID' => $id, 'content' => $e->getMessage(), 'isError' => true];
        }
    }

    /**
     * Make one request, folding every way it can fail into AiUnavailable.
     *
     * @template T of object
     * @param  Closure(): T  $request
     * @return T
     */
    private function call(Closure $request): object
    {
        try {
            $message = $request();
        } catch (APIStatusException $e) {
            throw AiUnavailable::callFailed($e->type?->value ?? $e->getMessage());
        } catch (Throwable $e) {
            throw AiUnavailable::callFailed($e->getMessage());
        }

        // Cached input still counts: it is billed, just at a tenth of the price.
        $usage = $message->usage;

        $input  = (int) $usage->inputTokens;
        $output = (int) $usage->outputTokens;
        $read   = (int) ($usage->cacheReadInputTokens ?? 0);
        $write  = (int) ($usage->cacheCreationInputTokens ?? 0);

        $this->tokens += $input + $output + $read + $write;

        $this->lastUsage = [
            'prompt_tokens'      => $input + $read + $write,
            'completion_tokens'  => $output,
            'cached_tokens'      => $read,
            'cache_write_tokens' => $write,
        ];

        // A safety decline comes back as a normal 200, so it has to be checked
        // before the content is read rather than caught as an error.
        if ($message->stopReason === 'refusal') {
            throw AiUnavailable::callFailed('the model declined this request');
        }

        return $message;
    }

    /** All the text in a reply, or null when there is none. */
    private function textOf(object $message): ?string
    {
        $text = collect($message->content)
            ->filter(fn($block) => ($block->type ?? null) === 'text')
            ->map(fn($block) => $block->text)
            ->implode("\n\n");

        return filled(trim($text)) ? $text : null;
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->apiKey);
    }
}
