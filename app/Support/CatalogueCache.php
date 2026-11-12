<?php

namespace App\Support;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Testimonial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The catalogue reads every page and chat turn repeats: the area lists, the
 * starter chips, the home cards and the reviews. Cached forever and flushed by
 * the model hooks on every write, so there is no staleness window - a home
 * marked sold is gone from the next request, not from the one after a TTL.
 *
 * Payloads are plain arrays of raw column values: cache.serializable_classes is
 * false, so a model in the cache would not unserialize. Cards are hydrated back
 * into models on read, which issues no queries and leaves Blade untouched.
 *
 * Listing SEARCH results are deliberately not here: they stay live so a filter
 * can never answer from stock that has moved.
 */
class CatalogueCache
{
    private const PREFIX = 'catalogue.';

    /** Every key this class owns, so flush() cannot miss one. */
    private const KEYS = ['suburbs', 'sale-suburbs', 'chips', 'home', 'testimonials'];

    /** The areas that hold published stock, for matching a visitor's wording. */
    public function suburbs(): Collection
    {
        return collect(Cache::rememberForever(
            self::PREFIX . 'suburbs',
            fn() => Property::published()->distinct()->pluck('suburb')->filter()->values()->all(),
        ));
    }

    /** The same, for-sale only, as the line the system prompt states. */
    public function saleSuburbs(): string
    {
        return Cache::rememberForever(
            self::PREFIX . 'sale-suburbs',
            fn() => Property::published()->forSale()->distinct()->orderBy('suburb')->pluck('suburb')->implode(', '),
        );
    }

    /** @return list<string> */
    public function chips(): array
    {
        return Cache::rememberForever(self::PREFIX . 'chips', fn() => $this->buildChips());
    }

    /**
     * The home page's three reads, as raw rows plus the sold count.
     *
     * @return array{featured: Collection<int, Property>, recentlySold: Collection<int, Property>, soldCount: int}
     */
    public function home(): array
    {
        $payload = Cache::rememberForever(self::PREFIX . 'home', function () {
            return [
                'featured' => $this->rows(
                    Property::published()->forSale()->with('images')
                        ->orderByDesc('is_featured')->orderByDesc('created_at')->take(6)->get()
                ),
                'recentlySold' => $this->rows(
                    Property::published()->sold()->with('images')
                        ->orderByDesc('sold_at')->take(3)->get()
                ),
                'soldCount' => Property::published()->sold()->count(),
            ];
        });

        return [
            'featured' => $this->properties($payload['featured']),
            'recentlySold' => $this->properties($payload['recentlySold']),
            'soldCount' => $payload['soldCount'],
        ];
    }

    /** Every published review, in display order; pages slice what they need. */
    public function testimonials(): Collection
    {
        return Testimonial::hydrate(Cache::rememberForever(
            self::PREFIX . 'testimonials',
            fn() => Testimonial::published()->get()->map->getAttributes()->all(),
        ));
    }

    /** The home page's featured subset, in the same order the query used. */
    public function featuredTestimonials(): Collection
    {
        return $this->testimonials()->where('is_featured', true)->take(3)->values();
    }

    /** Called from the model hooks and the seeder: every write starts cold. */
    public function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget(self::PREFIX . $key);
        }
    }

    /**
     * One question per kind of stock, in an area that actually holds it. A chip
     * is a promise that the question works, so it is built from stock that
     * exists rather than written by hand and hoped over.
     *
     * @return list<string>
     */
    private function buildChips(): array
    {
        $chips = [];

        if ($area = $this->busiest('apartment')) {
            $chips[] = 'Apartments in ' . $area . ' for sale';
        }

        // Whichever family-home type we hold. The noun has to match the type:
        // "homes in Arabian Ranches" searches houses, and a townhouse is not one.
        foreach (['townhouse' => 'Townhouses', 'house' => 'Villas'] as $type => $noun) {
            if ($area = $this->busiest($type)) {
                $chips[] = $noun . ' in ' . $area . ' for sale';

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

    /**
     * Raw columns plus the image rows, kept parallel so hydration can pair them
     * again. getAttributes(), not toArray(): casts stay unapplied so hydrate()
     * reproduces exactly what the database held.
     *
     * @param  Collection<int, Property>  $properties
     * @return list<array{property: array<string, mixed>, images: list<array<string, mixed>>}>
     */
    private function rows(Collection $properties): array
    {
        return $properties->map(fn(Property $property) => [
            'property' => $property->getAttributes(),
            'images' => $property->images->map->getAttributes()->all(),
        ])->all();
    }

    /** @param list<array{property: array<string, mixed>, images: list<array<string, mixed>>}> $rows */
    private function properties(array $rows): Collection
    {
        $models = Property::hydrate(array_column($rows, 'property'));

        foreach ($models as $index => $model) {
            $model->setRelation('images', PropertyImage::hydrate($rows[$index]['images']));
        }

        return $models;
    }
}
