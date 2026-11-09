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
     *
     * @throws AiUnavailable when the model cannot be reached or returns nothing
     */
    public function converse(string $system, array $messages, array $tools, Closure $runTool): string;

    /** Tokens the last call used, summed over tool rounds; zero before any call. */
    public function lastTokens(): int;
}
