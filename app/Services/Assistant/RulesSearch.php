<?php

namespace App\Services\Assistant;

use App\Models\ChatConversation;
use App\Models\Property;
use App\Services\Listings\PropertySearch;
use App\Support\Format;
use Illuminate\Support\Str;

/**
 * The free, no-model search the chat falls back to when there is no API key.
 *
 * QuickAnswers deliberately hands every browse question ("4-bedroom villas in
 * Dubai Hills under AED 8M", "apartments downtown", "villas to rent") to the model -
 * they need reading into criteria and running against the listings. With no key
 * that hand-off has nowhere to go, so those questions used to dead-end at
 * "Sorry, I can't answer" - including the assistant's own suggestion chips.
 *
 * This closes that gap without a model: it reads the obvious criteria out of the
 * message, runs the same PropertySearch the model's tool uses, and lists what
 * comes back straight from the database. It only ever reads published listings,
 * and when the message is not a search at all it points the visitor forwards
 * instead of guessing. Adding an API key upgrades the whole thing to the model -
 * this path simply stops being reached.
 */
class RulesSearch
{
    /** Enough to choose from in a chat window; more is a list, not an answer. */
    private const SHOW_LIMIT = 3;

    /**
     * A figure above this is not a price - it is a bot testing the parser, or a
     * fat-fingered number. Capping here keeps a huge run of digits from
     * overflowing the int cast when we scale by a unit like "m"/"million".
     */
    private const MAX_MONEY = 1.0e13;

    /**
     * Words that name a place too loosely to pin one suburb on their own - they
     * belong to several areas, or mean the whole city, or are too generic to
     * narrow anything ("hills" is in half our areas), so matching on them would
     * over-filter the results.
     */
    private const GENERIC_AREA_WORDS = ['dubai', 'uae', 'hills', 'estate', 'creek', 'harbour', 'park', 'springs', 'heights', 'gardens', 'greens', 'lake'];

    /**
     * What visitors type, mapped to the suburb names on the listings. Full names
     * are matched first, so "Dubai Marina" never depends on "marina". A matched
     * alias pins the area even when we have nothing there: the honest answer is
     * "none in that area", never every listing we have.
     */
    private const AREA_ALIASES = [
        'jvc'            => 'Jumeirah Village Circle',
        'jvt'            => 'Jumeirah Village Triangle',
        'jlt'            => 'Jumeirah Lake Towers',
        'marina'         => 'Dubai Marina',
        'downtown'       => 'Downtown Dubai',
        'dh'             => 'Dubai Hills Estate',
        'dubai hills'    => 'Dubai Hills Estate',
        'palm'           => 'Palm Jumeirah',
        'bb'             => 'Business Bay',
        'business bay'   => 'Business Bay',
        'creek harbour'  => 'Dubai Creek Harbour',
        'sohba'          => 'Sobha Hartland',
        'mbc'            => 'Mohammed Bin Rashid City',
        'mbr city'       => 'Mohammed Bin Rashid City',
        'barsha'         => 'Al Barsha',
        'furjan'         => 'Al Furjan',
        'springs'        => 'The Springs',
        'meadows'        => 'The Meadows',
        'ranches'        => 'Arabian Ranches',
        'arabian ranches' => 'Arabian Ranches',
    ];

    public function __construct(private readonly PropertySearch $search) {}

    /** @return array{text: string, kind: string} */
    public function answer(ChatConversation $conversation, string $message): array
    {
        $text = $this->normalise($message);
        $criteria = $this->parse($text);

        if (! $this->isSearch($text, $criteria)) {
            return $this->reply($this->guide($conversation), 'guide');
        }

        $result = $this->search->search($criteria, self::SHOW_LIMIT);
        $what = $this->describe($criteria);

        return $result['properties'] === []
            ? $this->reply($this->noneFound($what, $conversation), 'search_none')
            : $this->reply($this->found($result, $what), 'search');
    }

    /**
     * Read the criteria a visitor states in plain words. Deliberately conservative:
     * a field is only set when the message clearly says it, so a wrong guess never
     * filters listings out that the visitor would have wanted to see.
     *
     * @return array<string, mixed> criteria in the shape PropertySearch expects
     */
    private function parse(string $text): array
    {
        $criteria = [];

        if (preg_match('/\b(rent|renting|rental|lease|to rent|for rent)\b/', $text)) {
            $criteria['rent'] = true;
        }

        if (preg_match('/(\d+)\s*[- ]?(?:bed(?:room)?s?|br)\b/', $text, $m)) {
            $criteria['min_bedrooms'] = max(1, (int) $m[1]);
        }

        foreach (
            [
                'apartment' => '/\b(condo|condominium|apartment|flat|unit)s?\b/',
                'townhouse' => '/\b(townhouse|townhome|duplex|triplex)\b/',
                'acreage'   => '/\bfarm ?house|\bacre/',
                'land'      => '/\b(plot|lot|land|homesite|acreage)s?\b/',
                'house'     => '/\b(house|home|bungalow|villa|ranch|detached|single[- ]family)s?\b/',
            ] as $type => $pattern
        ) {
            if (preg_match($pattern, $text)) {
                $criteria['type'] = $type;

                break;
            }
        }

        $isRent = (bool) ($criteria['rent'] ?? false);

        // Rent is a few hundred to a few thousand a month; a sale figure is
        // tens of thousands up. The floor keeps "3 bedrooms" from reading as $3.
        $amounts = $this->amounts($text, $isRent ? 200 : 10_000);

        if (count($amounts) >= 2) {
            // A stated range: "between $400K and $600K", "$1,500 to $2,000".
            $criteria['min_price'] = min($amounts);
            $criteria['max_price'] = max($amounts);
        } elseif ($amounts !== []) {
            $one = $amounts[0];
            if (preg_match('/\b(above|over|more than|at least|minimum|from|upwards|plus)\b/', $text)) {
                $criteria['min_price'] = $one;
            } elseif (preg_match('/\b(around|about|approximately|approx)\b/', $text)) {
                // "around 500k" is a band either side, not a ceiling.
                $criteria['min_price'] = (int) round($one * 0.8);
                $criteria['max_price'] = (int) round($one * 1.2);
            } else {
                // A lone figure is a ceiling ("$500K", "under $500K").
                $criteria['max_price'] = $one;
            }
        }

        if (($area = $this->matchingArea($text)) !== null) {
            $criteria['area'] = $area;
        }

        return $criteria;
    }

    /** Is this actually a request to find a property, rather than small talk? */
    private function isSearch(string $text, array $criteria): bool
    {
        if ($criteria !== []) {
            return true;
        }

        return (bool) preg_match('/\b(homes?|houses?|listings?|available|for sale|buying|buyer|anything|show me|look)\b/', $text);
    }

    /**
     * Money amounts in the text, scaled by their unit. A bare number below the
     * floor is not money ("2 bedrooms", "18 days"), so those are ignored.
     *
     * @param  int  $floor  smallest unit-less figure to treat as a price
     * @return list<int>
     */
    private function amounts(string $text, int $floor): array
    {
        $units = [
            'k' => 1_000,
            'thousand' => 1_000,
            'm' => 1_000_000,
            'million' => 1_000_000,
            // Visitors from the subcontinent quote budgets in lakh and crore,
            // and they expect them read as money, not ignored.
            'lakh' => 100_000,
            'lac' => 100_000,
            'crore' => 10_000_000,
        ];
        $found = [];

        preg_match_all('/(\d+(?:\.\d+)?)\s*(k|thousand|m|million|lakh|lac|crore)?\b/', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $number = (float) $m[1];
            $unit = $m[2] ?? '';
            $value = $unit !== '' ? $number * $units[$unit] : $number;

            // Ignore figures that are not real money - too small to be a price,
            // or so large they would overflow the int cast below.
            if ($value > self::MAX_MONEY || ($unit === '' && $value < $floor)) {
                continue;
            }

            $found[] = (int) round($value);
        }

        return $found;
    }

    /**
     * Which suburb the visitor means, matched against the suburbs that actually
     * have listings so the answer can never point at an area we do not work in.
     * Prefers a full name ("Dubai Marina"); falls back to a distinctive single word
     * ("downtown" for "Downtown Dubai") only when that word belongs to one suburb.
     */
    private function matchingArea(string $text): ?string
    {
        $suburbs = Property::published()->distinct()->pluck('suburb')->filter()->values();

        foreach ($suburbs->sortByDesc(fn($s) => mb_strlen((string) $s)) as $suburb) {
            if (mb_stripos($text, (string) $suburb) !== false) {
                return $suburb;
            }
        }

        // "JVC", "marina", "the palm" - longest alias first so "dubai hills"
        // wins over "dh".
        foreach (collect(self::AREA_ALIASES)->sortByDesc(fn($suburb, $alias) => mb_strlen((string) $alias)) as $alias => $suburb) {
            if (preg_match('/\b' . preg_quote((string) $alias, '/') . '\b/', $text)) {
                return $suburb;
            }
        }

        // Unique, distinctive words: "Downtown" -> only "Downtown Dubai" has it.
        // Generic words are skipped so "Dubai" never pins a single suburb.
        $byWord = [];
        foreach ($suburbs as $suburb) {
            foreach (preg_split('/\s+/', (string) $suburb) as $word) {
                $word = Str::lower($word);
                if (mb_strlen($word) >= 5 && ! in_array($word, self::GENERIC_AREA_WORDS, true)) {
                    $byWord[$word][] = $suburb;
                }
            }
        }

        foreach ($byWord as $word => $owners) {
            if (count($owners) === 1 && mb_stripos($text, $word) !== false) {
                return $owners[0];
            }
        }

        return null;
    }

    /** The search in words, so the visitor sees what was looked for. */
    private function describe(array $criteria): string
    {
        $nouns = [
            'house' => 'house',
            'apartment' => 'apartment',
            'townhouse' => 'townhome',
            'land' => 'lot',
            'acreage' => 'estate',
        ];

        $price = match (true) {
            isset($criteria['min_price'], $criteria['max_price']) => 'from ' . $this->money($criteria['min_price']) . ' to ' . $this->money($criteria['max_price']),
            isset($criteria['max_price']) => 'under ' . $this->money($criteria['max_price']),
            isset($criteria['min_price']) => 'from ' . $this->money($criteria['min_price']),
            default => null,
        };

        $parts = array_filter([
            isset($criteria['min_bedrooms']) ? $criteria['min_bedrooms'] . '+ bedroom' : null,
            isset($criteria['type']) ? ($nouns[$criteria['type']] ?? Str::lower(config('agent.property_types.' . $criteria['type']))) : null,
            $criteria['area'] ?? null ? 'in ' . $criteria['area'] : null,
            $price,
            ($criteria['rent'] ?? false) ? 'to rent' : null,
        ]);

        return $parts ? ' (' . implode(' ', $parts) . ')' : '';
    }

    private function found(array $result, string $what): string
    {
        $lines = collect($result['properties'])->map(function (array $p) {
            $price = $p['rent'] ?? $p['price'] ?? null;
            $meta = array_filter([
                $price,
                isset($p['bedrooms']) ? $p['bedrooms'] . ' bed' : null,
                $p['area'] ?? null,
            ]);

            return '- ' . $p['title'] . ($meta ? ' (' . implode(', ', $meta) . ')' : '') . "\n" . $p['url'];
        })->implode("\n");

        $total = $result['total'];

        return sprintf(
            "I found %d %s%s - here are the latest:\n\n%s\n\nWant a viewing or the full list? Call %s on %s or reply here.",
            $total,
            $total === 1 ? 'listing' : 'listings',
            $what,
            $lines,
            config('agent.name'),
            config('agent.phone'),
        );
    }

    private function noneFound(string $what, ChatConversation $conversation): string
    {
        $agent = config('agent.name');

        $lead = $conversation->property
            ? 'Meanwhile, ask me anything about ' . $conversation->property->title . ' - price, size, or what it is like.'
            : 'Tell me what you are after and I will look again.';

        return sprintf(
            "I do not have any listings%s at the moment. %s Leave your details with %s on %s and I will pass them on for whatever comes next.",
            $what,
            $lead,
            $agent,
            config('agent.phone'),
        );
    }

    /** Not a search, and no rule fit: point the visitor somewhere useful. */
    private function guide(ChatConversation $conversation): string
    {
        $agent = config('agent.name');
        $phone = config('agent.phone');

        if ($conversation->property) {
            return sprintf(
                'For %s, %s can answer the details and arrange a viewing - call or WhatsApp on %s, or use the enquiry form on this page.',
                $conversation->property->title,
                $agent,
                $phone,
            );
        }

        return sprintf(
            "I can search our Dubai listings for you - just tell me the area (like Dubai Marina or Business Bay), how many bedrooms, and a budget, or whether you are renting.\n\nYou can also browse everything at [all our listings](%s), or call %s on %s.",
            route('for-sale'),
            $agent,
            $phone,
        );
    }

    private function money(int $value): string
    {
        return Format::price($value);
    }

    private function normalise(string $message): string
    {
        $text = Str::lower(trim($message));

        // "1,500,000" and "8,00,000" are one number each. A comma used as a
        // separator is followed by a space, so it survives this.
        $text = preg_replace('/(?<=\d),(?=\d)/', '', $text);

        $text = preg_replace('/[^\p{L}\p{N}@.\s-]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /** @return array{text: string, kind: string} */
    private function reply(string $text, string $kind): array
    {
        return ['text' => $text, 'kind' => $kind];
    }
}
