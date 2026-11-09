<?php

namespace App\Support;

use App\Models\Property;

/**
 * The starter questions offered above the chat input.
 *
 * Every one of them has to be answerable from the listing records alone: a
 * chip is a promise that this question works, so it is built from stock that
 * exists rather than written by hand and hoped over.
 */
final class AssistantSuggestions
{
    /** The word the visitor reads, and the one the rule layer reads back. */
    private const NOUNS = [
        'apartment' => 'Apartments',
        'townhouse' => 'Townhouses',
        'house'     => 'Villas',
    ];

    /** @return list<string> */
    public function for(?Property $property): array
    {
        return $property !== null ? $this->aboutListing() : $this->aboutStock();
    }

    /** Facts any published record can answer - no area, no stock involved. */
    private function aboutListing(): array
    {
        return [
            'Is it still available?',
            'What is the price?',
            'How many bedrooms?',
            'Is the price negotiable?',
        ];
    }

    /** One question per kind of stock, in an area that actually holds it. */
    private function aboutStock(): array
    {
        $chips = [];

        if ($area = $this->busiest('apartment')) {
            $chips[] = self::NOUNS['apartment'] . ' in ' . $area . ' for sale';
        }

        // Whichever family-home type we hold. The noun has to match the type:
        // "homes in Arabian Ranches" searches houses, and a townhouse is not one.
        foreach (['townhouse', 'house'] as $type) {
            if ($area = $this->busiest($type)) {
                $chips[] = self::NOUNS[$type] . ' in ' . $area . ' for sale';

                break;
            }
        }

        // No type stated: the rentals in an area are not always all one type.
        if ($area = $this->rentalArea()) {
            $chips[] = 'Anything to rent in ' . $area . '?';
        }

        $chips[] = 'What areas do you cover?';

        return $chips;
    }

    /** The area holding the most of a type, so a plain "search this area" chip always comes back with something. */
    private function busiest(string $type): ?string
    {
        return Property::published()
            ->forSale()
            ->where('type', $type)
            ->select('suburb')
            ->whereNotNull('suburb')
            ->where('suburb', '!=', '')
            ->groupBy('suburb')
            ->orderByRaw('count(*) desc')
            ->orderBy('suburb')
            ->value('suburb') ?: null;
    }

    /** Same, for whatever is on the rental book. */
    private function rentalArea(): ?string
    {
        $area = Property::published()
            ->forRent()
            ->select('suburb')
            ->whereNotNull('suburb')
            ->where('suburb', '!=', '')
            ->groupBy('suburb')
            ->orderByRaw('count(*) desc')
            ->orderBy('suburb')
            ->value('suburb');

        return is_string($area) && $area !== '' ? $area : null;
    }
}
