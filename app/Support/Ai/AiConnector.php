<?php

namespace App\Support\Ai;

use Closure;

/**
 * The one way this application talks to a language model.
 *
 * Everything above this line - services, controllers, views - knows only this
 * interface, so the vendor behind it can change, be switched off, or be faked
 * in tests without a single caller changing. Tests bind a fake implementation,
 * which is what keeps the suite offline, free and deterministic.
 */
interface AiConnector
{
    /**
     * Whether a real model is reachable. False means no credentials are set -
     * callers must degrade gracefully rather than fail.
     */
    public function isConfigured(): bool;

    /**
     * Run one prompt and return the model's text.
     *
     * @throws AiUnavailable when the model cannot be reached or returns nothing
     */
    public function complete(string $system, string $prompt): string;

    /**
     * Carry on a conversation in which the model may call tools, and return its
     * final reply once it has stopped calling them.
     *
     * The tool loop lives behind this method so nothing above it ever handles a
     * vendor's message objects. Callers describe the tools and supply one
     * closure that runs them; it returns the result as text for the model to
     * read, or throws ToolFailed to tell the model the call did not work.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages  oldest first, ending on the visitor
     * @param  list<array{name: string, description: string, inputSchema: array<string, mixed>}>  $tools
     * @param  Closure(string $name, array<string, mixed> $input): string  $runTool
     *
     * @throws AiUnavailable when the model cannot be reached or returns nothing
     */
    public function converse(string $system, array $messages, array $tools, Closure $runTool): string;

    /**
     * Tokens the most recent complete() or converse() call used, input and
     * output, summed over every tool round. Zero before any call. Recorded
     * against each reply so the agent can see what the assistant costs.
     */
    public function lastTokens(): int;
}
