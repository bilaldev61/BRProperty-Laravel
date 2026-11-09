<?php

namespace App\Services\Assistant\Kit;

use App\Models\Property;
use App\Services\Listings\PropertySearch;
use App\Support\PropertyFacts;
use AssistantKit\Contracts\Catalogue;

/**
 * The Kit's view of the listings. Everything here already existed: PropertySearch
 * owns which listings are visible, PropertyFacts owns what may be said about one.
 * The Kit only ever sees arrays, so nothing about Eloquent leaks into it.
 */
class PropertyCatalogue implements Catalogue
{
    private readonly PropertySearch $search;

    public function __construct()
    {
        $this->search = new PropertySearch();
    }

    public function search(array $criteria, int $limit): array
    {
        $result = $this->search->search(array_filter([
            'area'         => $criteria['area'] ?? null,
            'type'         => $this->typeKey($criteria['type'] ?? null),
            'min_bedrooms' => $criteria['beds'] ?? null,
            'min_price'    => $criteria['price_min'] ?? null,
            'max_price'    => $criteria['price_max'] ?? null,
            'rent'         => isset($criteria['deal']) ? $criteria['deal'] === 'rent' : null,
        ], fn($value) => $value !== null && $value !== ''), $limit);

        return [
            'total' => (int) $result['total'],
            'items' => array_map(fn(array $property) => [
                'slug'    => (string) $property['slug'],
                'title'   => (string) $property['title'],
                'url'     => (string) $property['url'],
                'summary' => $this->summary($property),
            ], $result['properties']),
        ];
    }

    public function find(string $slug): ?array
    {
        $property = $this->search->find($slug);

        return $property !== null ? PropertySearch::summary($property) : null;
    }

    /**
     * Keyed sentences. `listing` carries every fact the site already allows the
     * model to state; the rest are the keys the property pack asks for, so a
     * recorded fact can beat a deferral.
     */
    public function facts(array $item): array
    {
        $property = $this->search->find((string) ($item['slug'] ?? ''));

        if ($property === null) {
            return [];
        }

        return array_filter([
            'listing'      => $property->title . '. ' . implode(' ', PropertyFacts::lines($property)),
            'price'        => 'Price as advertised: ' . $property->priceDisplay() . '.',
            'bedrooms'     => $property->hasRooms() && $property->bedrooms
                ? 'Bedrooms: ' . $property->bedrooms . '. Bathrooms: ' . ($property->bathrooms ?: 'not recorded') . '.'
                : null,
            'size'         => $this->size($property),
            'availability' => 'Listing status: ' . $property->statusLabel() . '.',
            'furnishing'   => $property->furnishingLabel() ? 'Furnishing: ' . $property->furnishingLabel() . '.' : null,
            'handover'     => $this->handover($property),
            'parking'      => $property->hasRooms() && $property->carspaces
                ? 'Car spaces: ' . $property->carspaces . '.'
                : null,
        ], fn($fact) => filled($fact));
    }

    private function summary(array $property): string
    {
        return implode(' - ', array_filter([
            $property['price'] ?? null,
            $property['rent'] ?? null,
            $property['area'] ?? null,
        ]));
    }

    private function size(Property $property): ?string
    {
        $parts = array_filter([$property->floorDisplay(), $property->landDisplay()]);

        return $parts !== [] ? 'Size: ' . implode('. Land: ', $parts) . '.' : null;
    }

    private function handover(Property $property): ?string
    {
        $parts = array_filter([
            $property->completionLabel(),
            filled($property->handover) ? 'handover ' . $property->handover : null,
        ]);

        return $parts !== [] ? 'Completion: ' . implode(', ', $parts) . '.' : null;
    }

    /** The pack speaks labels ("Apartment"); the search wants the config key. */
    private function typeKey(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        foreach ((array) config('agent.property_types', []) as $key => $label) {
            if (mb_strtolower((string) $label) === mb_strtolower($type)) {
                return (string) $key;
            }
        }

        return null;
    }
}
