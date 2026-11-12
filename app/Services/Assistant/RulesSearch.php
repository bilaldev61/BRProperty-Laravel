<?php

namespace App\Services\Assistant;

use App\Models\ChatConversation;
use App\Services\Listings\PropertySearch;
use App\Support\CatalogueCache;
use App\Support\Format;
use Illuminate\Support\Str;

/**
 * The free, no-model fallback for browse questions ("4-bed villas in Dubai
 * Hills under AED 8M"). Reads the criteria, runs PropertySearch, lists what
 * comes back. Not a search: point the visitor forward instead of guessing.
 */
class RulesSearch
{
    /** Enough to choose from in a chat window; more is a list, not an answer. */
    private const SHOW_LIMIT = 3;

    /** Above this a "price" is a typo or a probe; the cap stops the int cast overflowing. */
    private const MAX_MONEY = 1.0e13;

    /** Too generic to pin one suburb on ("hills" is in half our areas). */
    private const GENERIC_AREA_WORDS = ['dubai', 'uae', 'hills', 'estate', 'creek', 'harbour', 'park', 'springs', 'heights', 'gardens', 'greens', 'lake'];

    /** Judgement, not a list - "compare with the Marina flat" is not a search. */
    private const NEEDS_JUDGEMENT = '/\b(compare|comparison|compared|better|worse|best|vs|versus|recommend|suggest|should i|why|explain)\b/';

    /** Someone asking how the transaction runs, not what is on the market. */
    private const PROCESS_ASKS = '/\b(process|procedure|steps?|works?|working|how (do|does|can|to)|what (do|should) i need|requirements?|documents?)\b/';

    /** Spelling drift included - "rennt" is what a hurrying visitor types. */
    private const RENT_WORDS = '/\bren+t(?:ing|al|s)?\b|\btenan(?:t|cy|cies)\b/';

    private const BUY_WORDS = '/\bbuy(?:ing)?\b|\bpurchas(?:e|ing)\b|\bsell(?:ing)?\b/';

    private const VIEWING_WORDS = '/\b(viewings?|inspection)\b/';

    /**
     * Abbreviations visitors type. A matched alias pins the area even with nothing
     * there: "none in that area" is honest, every listing we have is not.
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

        if (($steps = $this->process($text)) !== null) {
            return $this->reply($steps, 'process');
        }

        if (preg_match(self::NEEDS_JUDGEMENT, $text)) {
            return $this->reply($this->guide($conversation), 'guide');
        }

        $criteria = $this->parse($text);

        if (! $this->isSearch($text, $criteria)) {
            return $this->reply($this->guide($conversation), 'guide');
        }

        return $this->runSearch($conversation, $criteria);
    }

    /**
     * Process answers and searches, for the layer that runs ahead of the model.
     *
     * A process question ("how do I book a viewing?") gets its steps from config
     * for free. A stated filter gets a database query. Everything else - a
     * judgement, a message with no criterion - returns null and reaches the model.
     *
     * @return array{text: string, kind: string}|null
     */
    public function searchAnswer(ChatConversation $conversation, string $message): ?array
    {
        $text = $this->normalise($message);

        if (($steps = $this->process($text)) !== null) {
            return $this->reply($steps, 'process');
        }

        if (preg_match(self::NEEDS_JUDGEMENT, $text)) {
            return null;
        }

        $criteria = $this->parse($text);

        if ($criteria === [] || (count($criteria) === 1 && isset($criteria['type']))) {
            return null;
        }

        return $this->runSearch($conversation, $criteria);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{text: string, kind: string}
     */
    private function runSearch(ChatConversation $conversation, array $criteria): array
    {
        $result = $this->search->search($criteria, self::SHOW_LIMIT);
        $what = $this->describe($criteria);

        return $result['properties'] === []
            ? $this->reply($this->noneFound($what, $conversation), 'search_none')
            : $this->reply($this->found($result, $what), 'search');
    }

    /**
     * Read the stated criteria - conservative, so a wrong guess never filters out
     * listings the visitor wanted.
     *
     * @return array<string, mixed>
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
                'townhouse' => '/\b(townhouse|townhome|duplex|triplex)s?\b/',
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

        // Rent is hundreds a month, a sale tens of thousands; the floor keeps "3 bed" from reading as money.
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

    /**
     * "How does renting work?" - the steps, from config, not a search. A
     * figure in the message means they are already shopping, so the search
     * gets it instead.
     */
    private function process(string $text): ?string
    {
        if (preg_match('/\d/', $text) || ! preg_match(self::PROCESS_ASKS, $text)) {
            return null;
        }

        $topic = match (true) {
            (bool) preg_match(self::RENT_WORDS, $text)   => 'renting',
            (bool) preg_match(self::BUY_WORDS, $text)    => 'buying',
            (bool) preg_match(self::VIEWING_WORDS, $text) => 'viewing',
            default => null,
        };

        $steps = $topic ? config('agent.process.' . $topic) : null;

        if (! is_array($steps) || empty($steps['steps'])) {
            return null;
        }

        return $steps['label'] . ":\n\n- " . implode("\n- ", $steps['steps'])
            . "\n\n" . str_replace(':agent', config('agent.name'), $steps['note']);
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
     * Money amounts, scaled by unit. A bare figure below the floor is not money.
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
            // Lakh and crore are budgets, not noise.
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

            // Skip figures too small to be money or too large for the int cast.
            if ($value > self::MAX_MONEY || ($unit === '' && $value < $floor)) {
                continue;
            }

            $found[] = (int) round($value);
        }

        return $found;
    }

    /**
     * The suburb meant, matched against suburbs that have listings. Full name
     * first, then a word distinctive to exactly one suburb.
     */
    private function matchingArea(string $text): ?string
    {
        $suburbs = app(CatalogueCache::class)->suburbs();

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

        // Distinctive words only, so "Dubai" never pins a suburb.
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
            "I do not have any listings%s at the moment. %s If you leave your name and email here, %s will pass on whatever comes next - or call %s on %s.",
            $what,
            $lead,
            $agent,
            $agent,
            config('agent.phone'),
        );
    }

    /** Not a search, and no rule fit: point the visitor somewhere useful. */
    private function guide(ChatConversation $conversation): string
    {
        $agent = config('agent.name');
        $phone = config('agent.phone');

        // The last thing before the phone number, so a visitor who would rather
        // not call still leaves a way to be reached.
        $ask = $conversation->isLead()
            ? ''
            : "\n\nLeave your name and email here and {$agent} will come back to you on it.";

        if ($conversation->property) {
            return sprintf(
                'For %s, %s can answer the details and arrange a viewing - call or WhatsApp on %s, or use the enquiry form on this page.%s',
                $conversation->property->title,
                $agent,
                $phone,
                $ask,
            );
        }

        return sprintf(
            "I can search our Dubai listings for you - just tell me the area (like Dubai Marina or Business Bay), how many bedrooms, and a budget, or whether you are renting.\n\nYou can also browse everything at [all our listings](%s), or call %s on %s.%s",
            route('for-sale'),
            $agent,
            $phone,
            $ask,
        );
    }

    private function money(int $value): string
    {
        return Format::price($value);
    }

    private function normalise(string $message): string
    {
        $text = Str::lower(trim($message));

        // "1,500,000" is one number; a comma used as a separator has a space after it.
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
