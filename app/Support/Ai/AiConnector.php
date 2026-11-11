<?php

namespace App\Support\Ai;

use Closure;

/**
 * The one way this app talks to a model. Callers know only this interface, so
 * the vendor can change or be faked in tests without touching them.
 */
interface AiConnector
{
    /** False when no credentials are set - callers degrade instead of failing. */
    public function isConfigured(): bool;

    /**
     * Run one prompt and return the model's text.
     *
     * @throws AiUnavailable when the model cannot be reached or returns nothing
     */
    public function complete(string $system, string $prompt): string;

    /**
     * Continue a conversation where the model may call tools; returns the final
     * reply. The tool loop stays behind this method, so no caller ever handles a
     * vendor message object.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages  oldest first, ending on the visitor
     * @param  list<array{name: string, description: string, inputSchema: array<string, mixed>}>  $tools
     * @param  Closure(string $name, array<string, mixed> $input): string  $runTool
     * @param  string|null  $session  stable id for the conversation, so the provider routes every round to the same
     *                                endpoint and the cached prefix stays warm
     *
     * @throws AiUnavailable when the model cannot be reached or returns nothing
     */
    public function converse(string $system, array $messages, array $tools, Closure $runTool, ?string $session = null): string;

    /** Tokens the last call used, summed over tool rounds; zero before any call. */
    public function lastTokens(): int;

    /**
     * What each request of the last call used, oldest round first. Empty before
     * any call, and empty for connectors that cannot report it.
     *
     * @return list<array{round: int, model: string, prompt_tokens: int, completion_tokens: int, cached_tokens: int, cache_write_tokens: int, latency_ms: int, tools: list<string>}>
     */
    public function lastRequests(): array;
}
