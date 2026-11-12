<?php

namespace App\Support;

use App\Models\Property;

/**
 * Resolves a published listing by slug once per request. The chat controller
 * and the get_property tool both ask for the same slug in the same turn; this
 * memo is per-request only, so a listing edited mid-request is never served
 * stale from it across requests.
 */
class PublishedProperty
{
    /** @var array<string, Property|null> */
    private array $memo = [];

    public function bySlug(mixed $slug): ?Property
    {
        if (! filled($slug) || ! is_string($slug)) {
            return null;
        }

        // array_key_exists, not ??: a slug that does not exist is memoised too,
        // so a bad slug costs one query per request rather than one per ask.
        if (! array_key_exists($slug, $this->memo)) {
            $this->memo[$slug] = Property::published()->where('slug', $slug)->first();
        }

        return $this->memo[$slug];
    }
}
