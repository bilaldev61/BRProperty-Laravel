<?php

namespace App\Support\Ai;

/**
 * Estimated dollar cost of a model request from its token split and list price.
 * An estimate, not a bill: OpenRouter's activity log is the source of truth.
 */
class ModelCost
{
    /** Per-1M rates, or null when the model has no listed price (never guessed). */
    public static function rates(string $model): ?array
    {
        // A free slug and a self-hosted model both cost nothing per token.
        if (str_ends_with($model, ':free') || static::category($model) === 'local') {
            return ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0];
        }

        // Slugs carry dots ("5.5"), so look up the whole map rather than config dot notation.
        return config('ai.pricing', [])[$model] ?? null;
    }

    /** Which panel bucket a model falls in: your own box, a free slug, or a paid cloud model. */
    public static function category(string $model): string
    {
        if (in_array($model, config('ai.local_models', []), true)) {
            return 'local';
        }

        return str_ends_with($model, ':free') ? 'free' : 'paid';
    }

    /** Cached input bills at a tenth, a cache write at a premium; only the rest is fresh. */
    public static function estimate(string $model, int $prompt, int $cached, int $written, int $completion): ?float
    {
        $rates = static::rates($model);

        if ($rates === null) {
            return null;
        }

        $fresh = max(0, $prompt - $cached - $written);

        return ($fresh * $rates['input']
            + $cached * $rates['cache_read']
            + $written * $rates['cache_write']
            + $completion * $rates['output']) / 1_000_000;
    }

    /** Enough decimals to see a cheap model's cost; an unpriced model is a dash, not $0. */
    public static function format(?float $cost): string
    {
        return match (true) {
            $cost === null => '—',
            $cost == 0.0   => '$0.00',
            $cost < 0.01   => '$' . number_format($cost, 4),
            default        => '$' . number_format($cost, 2),
        };
    }
}
