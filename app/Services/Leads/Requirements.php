<?php

namespace App\Services\Leads;

use Illuminate\Support\Str;

/**
 * What a caller is looking for, in one clean shape - read from whatever the
 * voice agent's post-call analysis extracted.
 *
 * The analysis is written by a model from a spoken conversation, so it comes
 * in every form: "$600K", "between 2 and 3 million", 1200000, "ranch house",
 * "next month", "half an acre", "2,400 sq ft". This turns all of it into fields
 * the matcher and the qualifier can rely on, and leaves anything it cannot read
 * as null rather than guessing. (South-Asian units - crore, lakh, marla, kanal -
 * are still understood too, so the same parser serves any market.)
 *
 * Field names it looks for (aliases in brackets) are listed in
 * docs/phone-agent.md - configure the same names in the voice agent.
 */
class Requirements
{
    public const TIMELINES = ['asap', '1_3_months', '3_6_months', '6_plus_months', 'browsing'];

    private const UNITS = ['crore' => 10_000_000, 'cr' => 10_000_000, 'lakh' => 100_000, 'lac' => 100_000, 'million' => 1_000_000, 'thousand' => 1_000, 'k' => 1_000, 'm' => 1_000_000];

    /**
     * @param  array<string, mixed>  $data  the provider's custom analysis fields
     * @return array<string, mixed>
     */
    public static function fromAnalysis(array $data): array
    {
        $data = array_change_key_case($data, CASE_LOWER);
        $get = function (string ...$keys) use ($data) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                    return $data[$key];
                }
            }

            return null;
        };

        [$budgetMin, $budgetMax] = self::budget(
            $get('budget_min_pkr', 'budget_min', 'min_budget'),
            $get('budget_max_pkr', 'budget_max', 'max_budget'),
            $get('budget', 'price_range'),
        );

        return [
            'name'                 => self::text($get('name', 'caller_name', 'customer_name', 'full_name'), 120),
            'email'                => filter_var($get('email', 'email_address'), FILTER_VALIDATE_EMAIL) ?: null,
            'intent'               => self::intent($get('intent', 'purpose', 'looking_to', 'buy_or_sell')),
            'property_types'       => self::types($get('property_type', 'property_types', 'type')),
            'areas'                => self::areas($get('areas', 'area', 'preferred_areas', 'location', 'locations')),
            'budget_min'           => $budgetMin,
            'budget_max'           => $budgetMax,
            'bedrooms_min'         => self::integer($get('bedrooms_min', 'bedrooms', 'beds', 'min_bedrooms')),
            'land_marla_min'       => self::marla($get('land_size', 'size', 'plot_size', 'land_marla_min')),
            'timeline'             => self::timeline($get('timeline', 'timeframe', 'when', 'purchase_timeline')),
            'payment'              => self::payment($get('payment', 'payment_method', 'financing')),
            'appointment_requested' => self::boolean($get('appointment_requested', 'wants_viewing', 'viewing_requested', 'wants_appointment')),
            'preferred_time'       => self::text($get('preferred_time', 'preferred_viewing_time', 'availability'), 200),
            'property_of_interest' => self::text($get('property_of_interest', 'property', 'listing', 'property_slug'), 255),
            'selling_property'     => self::text($get('selling_property', 'property_to_sell', 'seller_property'), 500),
            'notes'                => self::text($get('notes', 'other_notes', 'summary_notes'), 2000),
        ];
    }

    /** Requirements that say something - the rest are null or empty. */
    public static function stated(array $requirements): array
    {
        return array_filter($requirements, fn($v) => $v !== null && $v !== [] && $v !== false && $v !== 'unknown');
    }

    /** New answers win; what this call did not mention keeps the earlier answer. */
    public static function merge(array $earlier, array $new): array
    {
        $merged = $earlier;
        foreach ($new as $key => $value) {
            if ($value !== null && $value !== [] && $value !== 'unknown') {
                $merged[$key] = $value;
            } elseif (! array_key_exists($key, $merged)) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /** @return array{0: int|null, 1: int|null} */
    private static function budget(mixed $min, mixed $max, mixed $range): array
    {
        $min = self::money($min);
        $max = self::money($max);

        if (($min === null || $max === null) && is_string($range) && $range !== '') {
            $text = Str::lower($range);
            $amounts = self::amounts($text);

            if (count($amounts) >= 2) {
                [$min, $max] = [$min ?? min($amounts), $max ?? max($amounts)];
            } elseif (count($amounts) === 1) {
                $one = $amounts[0];
                if (preg_match('/\b(under|below|less than|max(imum)?|up ?to|within|tak)\b/', $text)) {
                    $max ??= $one;
                } elseif (preg_match('/\b(above|over|more than|min(imum)?|at least|se zyada)\b/', $text)) {
                    $min ??= $one;
                } elseif (preg_match('/\b(around|about|approx\w*|roughly|lagbhag|takreeban)\b/', $text)) {
                    // "around 2 crore": a band either side, so the matcher has room.
                    $min ??= (int) round($one * 0.85);
                    $max ??= (int) round($one * 1.15);
                } else {
                    // "my budget is 2 crore" is a ceiling, not a target.
                    $max ??= $one;
                }
            }
        }

        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [$min, $max];
    }

    private static function money(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return $value > 0 ? (int) round($value) : null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $amounts = self::amounts(Str::lower($value));

        return $amounts[0] ?? null;
    }

    /** @return list<int> every money amount in the text, ranges expanded */
    private static function amounts(string $text): array
    {
        $text = str_replace([',', '$', 'usd', 'pkr', 'rs'], ['', ' ', '', '', ''], $text);
        // "2-3 crore", "2 to 3 crore", "$2-3 million": the unit belongs to both numbers.
        $text = preg_replace('/(\d+(?:\.\d+)?)\s*(?:-|–|to|and|se)\s*(\d+(?:\.\d+)?)\s*(crore|cr|lakh|lac|million|thousand|k|m)\b/u', '$1 $3 $2 $3', $text);

        preg_match_all('/(\d+(?:\.\d+)?)\s*(crore|cr|lakh|lac|million|thousand|k|m)?\b/', $text, $found, PREG_SET_ORDER);

        $amounts = [];
        foreach ($found as $m) {
            $number = (float) $m[1];
            $unit = $m[2] ?? '';
            $value = $unit !== '' ? $number * self::UNITS[$unit] : $number;

            // A bare small number in a budget sentence is not money ("2 bedrooms").
            if ($unit === '' && $value < 100_000) {
                continue;
            }
            $amounts[] = (int) round($value);
        }

        return $amounts;
    }

    private static function intent(mixed $value): string
    {
        $text = Str::lower((string) $value);

        return match (true) {
            (bool) preg_match('/\b(sell|selling|seller|bechna)\b/', $text)            => 'sell',
            (bool) preg_match('/\b(rent|renting|tenant|lease|kiraya)\b/', $text)       => 'rent',
            (bool) preg_match('/\b(buy|buying|buyer|purchase|invest|khareed)\w*/', $text) => 'buy',
            default                                                                     => 'unknown',
        };
    }

    /** @return list<string> config('agent.property_types') keys */
    private static function types(mixed $value): array
    {
        $text = Str::lower(is_array($value) ? implode(' ', $value) : (string) $value);
        $map = [
            'house'     => '/\b(house|home|bungalow|villa|ghar|kothi|single[- ]?family|detached|ranch)\b/',
            'apartment' => '/\b(apartment|flat|condo|condinium|unit)s?\b/',
            'townhouse' => '/\b(portion|upper|lower|townhouse|townhome|duplex|triplex)\b/',
            'land'      => '/\b(plot|land|lot|homesite)s?\b/',
            'acreage'   => '/\b(farm ?house|acreage|\d+(?:\.\d+)?\s*acres?)\b/',
        ];

        return array_keys(array_filter($map, fn($pattern) => preg_match($pattern, $text)));
    }

    /** @return list<string> */
    private static function areas(mixed $value): array
    {
        $parts = is_array($value) ? $value : preg_split('/\s*(?:,|;|\/|\band\b|\bor\b)\s*/i', (string) $value);

        return array_values(array_unique(array_filter(array_map(
            fn($part) => self::text($part, 60),
            $parts ?: [],
        ))));
    }

    /**
     * Minimum lot / land size, in the site's configured area unit (square feet
     * for the US). Reads "2,400 sq ft", "half an acre", "0.75 acres"; the legacy
     * Marla / Kanal forms still work for other markets.
     */
    private static function marla(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value > 0 ? (int) $value : null;
        }

        $text = Str::lower((string) $value);

        // US: square feet.
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:sq\.?\s*ft|sqft|square\s*feet|feet2|ft²)/', $text, $m)) {
            $size = (int) round((float) str_replace(',', '', $m[1]));

            return $size > 0 ? $size : null;
        }

        // US: acres (including spoken halves) -> square feet.
        if (preg_match('/(half|a\s*half|one\s*half)|(\d+(?:\.\d+)?)\s*(?:1\/2)?\s*acres?/', $text, $m)) {
            $acres = ! empty($m[1]) ? 0.5 : (float) $m[2];

            return $acres > 0 ? (int) round($acres * 43_560) : null;
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*kanal/', $text, $m)) {
            return (int) round((float) $m[1] * 20);
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*marla/', $text, $m)) {
            return (int) round((float) $m[1]);
        }

        return null;
    }

    private static function timeline(mixed $value): string
    {
        $text = Str::lower(str_replace('_', ' ', (string) $value));

        return match (true) {
            in_array(str_replace(' ', '_', $text), self::TIMELINES, true)                                   => str_replace(' ', '_', $text),
            (bool) preg_match('/\b(asap|immediate|urgent|right away|this (week|month)|jaldi|foran)/', $text)  => 'asap',
            (bool) preg_match('/\b(1|one|2|two|3|three) ?(-|to)? ?(months?)|next month|1 3 months/', $text) => '1_3_months',
            (bool) preg_match('/\b(4|5|6|four|five|six) ?months?|3 6 months|few months/', $text)           => '3_6_months',
            (bool) preg_match('/\b(next year|year|6 plus|6\+|later)\b/', $text)                              => '6_plus_months',
            (bool) preg_match('/\b(brows|just looking|not sure|no rush|researching|exploring)/', $text)     => 'browsing',
            default                                                                                          => 'unknown',
        };
    }

    private static function payment(mixed $value): string
    {
        $text = Str::lower((string) $value);

        return match (true) {
            (bool) preg_match('/\b(cash|ready|full payment|naqd)\b/', $text)        => 'cash',
            (bool) preg_match('/\b(installment|instalment|qist|plan)\w*/', $text)   => 'installments',
            (bool) preg_match('/\b(loan|mortgage|bank|financ)\w*/', $text)          => 'loan',
            default                                                                  => 'unknown',
        };
    }

    private static function integer(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value > 0 ? (int) $value : null;
        }

        return preg_match('/(\d+)/', (string) $value, $m) && (int) $m[1] > 0 ? (int) $m[1] : null;
    }

    private static function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'haan', 'ji'], true);
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $text === '' || in_array(Str::lower($text), ['null', 'none', 'n/a', 'unknown'], true) ? null : Str::limit($text, $max, '');
    }
}
