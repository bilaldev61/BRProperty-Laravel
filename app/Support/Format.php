<?php

namespace App\Support;

/**
 * Local number formatting, driven by config('agent.format').
 *
 * Pakistan prices are quoted in crore and lakh rather than millions, and land is
 * measured in Marla and Kanal rather than square metres. Both are switchable in
 * config so the same codebase works for another market - Dubai runs on dirhams
 * at western style, with the ISO code in front (AED 2,400,000).
 */
class Format
{
    public const MARLA_PER_KANAL = 20;

    public static function price(?int $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        $currency = config('agent.format.price.currency', 'PKR');

        if (config('agent.format.price.style') !== 'subcontinent') {
            // Western style: use a leading symbol when we know one (USD -> $1,250,000),
            // otherwise fall back to the ISO code + space (PKR 42,500,000).
            $symbol = static::currencySymbol($currency);

            return $symbol !== ''
                ? $symbol . number_format($amount)
                : $currency . ' ' . number_format($amount);
        }

        return $currency . ' ' . static::subcontinentAmount($amount);
    }

    /** ISO code -> display symbol. Unknown codes render as the code itself. */
    public static function currencySymbol(?string $currency): string
    {
        return match ($currency) {
            'USD' => '$',
            'EUR' => "\u{20AC}",
            'GBP' => "\u{00A3}",
            // Dirhams are quoted as a code rather than a symbol, locally and
            // on the portals, so the prefix stays plain here too.
            'AED' => 'AED ',
            default => '',
        };
    }

    /** 42_500_000 -> "4.25 Crore"; 8_500_000 -> "85 Lakh"; 90_000 -> "90,000" */
    public static function subcontinentAmount(int $amount): string
    {
        [$divisor, $unit] = match (true) {
            $amount >= 10_000_000 => [10_000_000, 'Crore'],
            $amount >= 100_000    => [100_000, 'Lakh'],
            default               => [1, ''],
        };

        if ($unit === '') {
            return number_format($amount);
        }

        $value = $amount / $divisor;

        // Two decimals, but never show trailing zeros: 4.00 -> 4, 4.50 -> 4.5
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $formatted . ' ' . $unit;
    }

    /** 2450 -> "2,450 sq ft" (US) | "2,450 m²" (metric) | "1 Kanal 5 Marla" (marla) */
    public static function area(?int $size): ?string
    {
        if ($size === null) {
            return null;
        }

        $unit = config('agent.format.area.unit');

        if ($unit === 'sqft') {
            return number_format($size) . ' sq ft';
        }

        if ($unit !== 'marla') {
            return number_format($size) . ' m²';
        }

        $kanal = intdiv($size, static::MARLA_PER_KANAL);
        $marla = $size % static::MARLA_PER_KANAL;

        return match (true) {
            $kanal && $marla => "{$kanal} Kanal {$marla} Marla",
            (bool) $kanal    => $kanal . ' Kanal',
            default          => $marla . ' Marla',
        };
    }

    /** Compact form for the tight card meta row: "1K 5M" style is unreadable, so keep words. */
    public static function areaShort(?int $size): ?string
    {
        return static::area($size);
    }

    public static function areaUnitLabel(): string
    {
        return match (config('agent.format.area.unit')) {
            'sqft'  => 'Square Feet',
            'marla' => 'Marla',
            default => 'm²',
        };
    }
}
