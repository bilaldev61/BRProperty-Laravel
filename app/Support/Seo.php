<?php

namespace App\Support;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Assembles the structured data for the site.
 *
 * Everything goes out in a single @graph rather than a scatter of separate
 * scripts, so nodes can reference one another by @id - the listing points at the
 * agent who is selling it, the breadcrumb points at the page, and Google reads
 * one connected description of the business instead of several disjoint ones.
 */
class Seo
{
    public static function agentId(): string
    {
        return url('/') . '#agent';
    }

    public static function websiteId(): string
    {
        return url('/') . '#website';
    }

    /** The business itself: NAP, hours, geo and the areas it covers. */
    public static function agent(): array
    {
        $a = config('agent');

        return array_filter([
            '@type'      => 'RealEstateAgent',
            '@id'        => static::agentId(),
            'name'       => $a['agency'],
            'legalName'  => $a['agency'],
            'url'        => url('/'),
            'image'      => Media::agent('photo'),
            'logo'       => Media::agent('logo_mark'),
            'description' => $a['seo']['description'],
            'telephone'  => $a['phone'],
            'email'      => $a['email'],
            'priceRange' => '$$',
            'currenciesAccepted' => $a['format']['price']['currency'],
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $a['office']['street'],
                'addressLocality' => $a['office']['suburb'],
                'addressRegion'   => $a['office']['state'],
                'postalCode'      => $a['office']['postcode'] ?: null,
                'addressCountry'  => $a['office']['country'],
            ],
            'geo' => $a['office']['lat'] ? [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $a['office']['lat'],
                'longitude' => (float) $a['office']['lng'],
            ] : null,
            'openingHoursSpecification' => static::openingHours(),
            'areaServed' => array_map(
                fn($area) => ['@type' => 'Place', 'name' => $area],
                $a['service_areas'],
            ),
            'employee' => [
                '@type'    => 'Person',
                'name'     => $a['name'],
                'jobTitle' => $a['title'],
                'image'    => Media::agent('photo'),
                'worksFor' => ['@id' => static::agentId()],
            ],
            'sameAs' => array_values(array_filter($a['social'])),
        ], fn($v) => $v !== null && $v !== []);
    }

    /** Opening hours as machine-readable day/time pairs, not just a label. */
    public static function openingHours(): array
    {
        $days = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];

        $out = [];

        foreach (config('agent.hours', []) as $label => $hours) {
            // "10:00am - 8:00pm" -> opens/closes; anything else (e.g. "By
            // appointment") has no machine time and is skipped.
            if (! preg_match('/(\d{1,2}(?::\d{2})?\s*[ap]m)\s*[-–]\s*(\d{1,2}(?::\d{2})?\s*[ap]m)/i', $hours, $m)) {
                continue;
            }

            $matched = [];
            foreach ($days as $key => $name) {
                if (Str::contains(Str::lower($label), $key) || Str::contains(Str::lower($label), Str::substr($key, 0, 3))) {
                    $matched[] = $name;
                }
            }

            // "Monday - Thursday" names both ends; fill in the span between them.
            if (count($matched) === 2 && Str::contains($label, ['-', '–'])) {
                $names = array_values($days);
                $from = array_search($matched[0], $names, true);
                $to = array_search($matched[1], $names, true);
                if ($from !== false && $to !== false && $to > $from) {
                    $matched = array_slice($names, $from, $to - $from + 1);
                }
            }

            if ($matched === []) {
                continue;
            }

            $out[] = [
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => $matched,
                'opens'     => static::time($m[1]),
                'closes'    => static::time($m[2]),
            ];
        }

        return $out;
    }

    private static function time(string $value): string
    {
        return date('H:i', strtotime(str_replace(' ', '', $value)));
    }

    /** The site, with the search box Google can surface under the listing. */
    public static function website(): array
    {
        return [
            '@type'      => 'WebSite',
            '@id'        => static::websiteId(),
            'url'        => url('/'),
            'name'       => config('agent.seo.site_name'),
            'publisher'  => ['@id' => static::agentId()],
            'inLanguage' => str_replace('_', '-', config('agent.seo.locale')),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => route('for-sale') . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @param array<int, array{name: string, url: string|null}> $trail */
    public static function breadcrumbs(array $trail): ?array
    {
        if (count($trail) < 2) {
            return null;
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($trail)->values()->map(fn($crumb, $i) => array_filter([
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $crumb['name'],
                'item'     => $crumb['url'] ?? null,
            ]))->all(),
        ];
    }

    /**
     * Encode the finished graph.
     *
     * This lives in PHP rather than the Blade template on purpose: "@context" in
     * template text is compiled as a Blade directive and silently mangles the
     * JSON. Keeping every @-prefixed key out of Blade avoids the whole class of
     * bug.
     */
    public static function document(array $nodes): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values(array_filter($nodes))],
            // The result is printed raw inside a <script type="application/ld+json">
            // block, so tag characters must be hex-escaped: a title containing
            // "</script>" would otherwise break out of the tag and execute.
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );
    }

    /** @param array<int, array{0: string, 1: string}> $pairs question => answer */
    public static function faq(array $pairs): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn($pair) => [
                '@type' => 'Question',
                'name'  => $pair[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $pair[1]],
            ], $pairs),
        ];
    }

    /** A results page, so Google knows the listings are a set. */
    public static function itemList(Collection $properties, string $name): array
    {
        return [
            '@type'           => 'ItemList',
            'name'            => $name,
            'numberOfItems'   => $properties->count(),
            'itemListElement' => $properties->values()->map(fn(Property $p, $i) => [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'url'      => route('properties.show', $p),
                'name'     => $p->title,
            ])->all(),
        ];
    }

    /** One property, linked back to the agent selling it. */
    public static function property(Property $property): array
    {
        $isSold = $property->status === 'sold';
        $isRentalOnly = $property->status === 'rental';

        // A pure rental carries its rent as the offer (with the billing period);
        // a for-sale listing keeps the sale price. Dual listings surface the sale
        // offer here and the rent on the page copy.
        $offerAmount = $isRentalOnly ? $property->rent_price : $property->price;

        $offers = $offerAmount ? array_filter([
            '@type'         => 'Offer',
            'price'         => $offerAmount,
            'priceCurrency' => config('agent.format.price.currency'),
            'availability'  => match (true) {
                $isRentalOnly => $property->rent_status === 'let_agreed'
                    ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/InStock',
                $property->status === 'under_offer' => 'https://schema.org/LimitedAvailability',
                default      => 'https://schema.org/InStock',
            },
            'url'           => route('properties.show', $property),
            'seller'        => ['@id' => static::agentId()],
            'priceSpecification' => $isRentalOnly ? [
                '@type'    => 'UnitPriceSpecification',
                'price'    => $property->rent_price,
                'priceCurrency' => config('agent.format.price.currency'),
                'unitText' => $property->rent_period === 'year' ? 'YEAR' : 'MONTH',
                'referenceQuantity' => [
                    '@type' => 'QuantitativeValue',
                    'value' => 1,
                    'unitCode' => $property->rent_period === 'year' ? 'ANN' : 'MON',
                ],
            ] : null,
        ], fn($v) => $v !== null && $v !== []) : null;

        return array_filter([
            '@type' => match ($property->type) {
                'apartment' => 'Apartment',
                'land'      => 'Place',
                default     => 'SingleFamilyResidence',
            },
            '@id'         => route('properties.show', $property) . '#property',
            'name'        => $property->title,
            'url'         => route('properties.show', $property),
            'description' => Str::limit(strip_tags($property->description), 300),
            'image'       => $property->images->map(fn($i) => $i->url())->values()->all()
                ?: [$property->heroImageUrl()],
            'numberOfRooms'          => $property->bedrooms ?: null,
            'numberOfBathroomsTotal' => $property->bathrooms ?: null,
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $property->address,
                'addressLocality' => $property->suburb,
                'addressRegion'   => $property->state,
                'postalCode'      => $property->postcode ?: null,
                'addressCountry'  => config('agent.office.country'),
            ],
            'geo' => $property->latitude ? [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $property->latitude,
                'longitude' => (float) $property->longitude,
            ] : null,
            'floorSize' => $property->floor_size ? [
                '@type' => 'QuantitativeValue',
                'value' => $property->floor_size,
                'unitCode' => 'FTK',
            ] : null,
            'offers' => $isSold ? null : $offers,
        ], fn($v) => $v !== null && $v !== []);
    }
}
