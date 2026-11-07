<?php

namespace App\Support\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use Closure;
use Throwable;

/**
 * The real connector: one call to Claude, one string back.
 *
 * Every failure - no credentials, a network problem, a rate limit, a refusal,
 * an empty body - leaves here as AiUnavailable, so callers have exactly one
 * thing to catch and the agent always falls back to writing it themselves.
 */
class ClaudeConnector implements AiConnector
{
    /** Opt-in header for the server-side `fallbacks: 'default'` retry below. */
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private int $tokens = 0;

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

        // `thinking` is deliberately omitted: Claude Opus 5 runs adaptive
        // thinking by default, which is what we want, and maxTokens is the
        // real cost ceiling on a short reply.
        $message = $this->call(fn () => $this->client()->messages->create(
            maxTokens: $this->maxTokens,
            messages: [['role' => 'user', 'content' => $prompt]],
            model: $this->model,
            system: $system,
            requestOptions: ['timeout' => (float) $this->timeout],
        ));

        return $this->textOf($message) ?? throw AiUnavailable::emptyResponse();
    }

    public function converse(string $system, array $messages, array $tools, Closure $runTool): string
    {
        if (! $this->isConfigured()) {
            throw AiUnavailable::notConfigured();
        }

        $this->tokens = 0;

        for ($round = 0; $round <= $this->maxToolRounds; $round++) {
            $message = $this->call(fn () => $this->client()->beta->messages->create(
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
                outputConfig: ['effort' => $this->chatEffort],
                system: $system,
                tools: $tools,
                betas: [self::FALLBACK_BETA],
                requestOptions: ['timeout' => (float) $this->timeout],
            ));

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
        $this->tokens += $usage->inputTokens + $usage->outputTokens
            + ($usage->cacheReadInputTokens ?? 0) + ($usage->cacheCreationInputTokens ?? 0);

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
            ->filter(fn ($block) => ($block->type ?? null) === 'text')
            ->map(fn ($block) => $block->text)
            ->implode("\n\n");

        return filled(trim($text)) ? $text : null;
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->apiKey);
    }
}
