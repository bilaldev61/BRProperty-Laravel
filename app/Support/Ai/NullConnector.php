<?php

namespace App\Support\Ai;

use Closure;

/**
 * What the container binds when no API key is set.
 *
 * It exists so the rest of the app never has to ask "is AI switched on?" with a
 * null check. Callers ask `isConfigured()` and hide the feature; if one calls
 * `complete()` anyway it fails loudly here rather than half-working in
 * production.
 */
class NullConnector implements AiConnector
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function complete(string $system, string $prompt): string
    {
        throw AiUnavailable::notConfigured();
    }

    public function converse(string $system, array $messages, array $tools, Closure $runTool): string
    {
        throw AiUnavailable::notConfigured();
    }

    public function lastTokens(): int
    {
        return 0;
    }
}
