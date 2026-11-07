<?php

namespace App\Support\Ai;

use RuntimeException;

/**
 * The model could not be reached, or had nothing to say.
 *
 * Always recoverable: the agent writes the reply themselves, exactly as they
 * did before this feature existed. Never let this reach the user as a 500.
 */
class AiUnavailable extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('No AI credentials are configured.');
    }

    public static function emptyResponse(): self
    {
        return new self('The model returned no usable text.');
    }

    public static function callFailed(string $reason): self
    {
        return new self("The model could not be reached: {$reason}");
    }
}
