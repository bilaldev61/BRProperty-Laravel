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
    /** @return list<string> */
    public function for(?Property $property): array
    {
        return $property !== null ? $this->aboutListing() : app(CatalogueCache::class)->chips();
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
}
