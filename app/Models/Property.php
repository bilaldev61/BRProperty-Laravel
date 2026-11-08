<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Format;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'features'    => 'array',
            'nearby'      => 'array',
            'sold_at'     => 'date',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'is_for_rent' => 'boolean',
            'rent_price'  => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $property) {
            if (blank($property->slug)) {
                // Append the area for uniqueness and for the keyword, but not when the
                // headline already names it - "Apartment in Gulberg" should not slug to
                // ...-gulberg-gulberg. Matching on the area's leading word is enough:
                // "DHA Lahore" is already covered by a title mentioning "DHA Phase 6".
                $title = Str::slug($property->title);
                $lead  = Str::before(Str::slug((string) $property->suburb), '-');

                $source = ($lead && ! Str::contains($title, $lead))
                    ? $property->title . ' ' . $property->suburb
                    : $property->title;

                $property->slug = static::uniqueSlug($source);
            }
        });
    }

    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)->orderBy('sort_order');
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeForSale(Builder $query): Builder
    {
        return $query->whereIn('status', ['for_sale', 'under_offer']);
    }

    public function scopeSold(Builder $query): Builder
    {
        return $query->where('status', 'sold');
    }

    /**
     * Active rentals. The rental side is independent of the sale side, so a
     * property listed for sale can also appear here, and a pure rental (status
     * 'rental') never leaks onto the for-sale lists.
     */
    public function scopeForRent(Builder $query): Builder
    {
        return $query->where('is_for_rent', true)
            ->whereIn('rent_status', ['available', 'let_agreed']);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['suburb'] ?? null, fn($q, $v) => $q->where('suburb', $v))
            ->when($filters['type'] ?? null, fn($q, $v) => $q->where('type', $v))
            ->when($filters['beds'] ?? null, fn($q, $v) => $q->where('bedrooms', '>=', (int) $v))
            ->when($filters['baths'] ?? null, fn($q, $v) => $q->where('bathrooms', '>=', (int) $v))
            ->when($filters['min'] ?? null, fn($q, $v) => $q->where('price', '>=', (int) $v))
            ->when($filters['max'] ?? null, fn($q, $v) => $q->where('price', '<=', (int) $v))
            ->when($filters['rent_min'] ?? null, fn($q, $v) => $q->where('rent_price', '>=', (int) $v))
            ->when($filters['rent_max'] ?? null, fn($q, $v) => $q->where('rent_price', '<=', (int) $v))
            ->when($filters['q'] ?? null, function ($q, $v) {
                $q->where(fn($sub) => $sub
                    ->where('title', 'like', "%{$v}%")
                    ->orWhere('suburb', 'like', "%{$v}%")
                    ->orWhere('address', 'like', "%{$v}%"));
            });
    }

    public function heroImage(): ?PropertyImage
    {
        return $this->images->first();
    }

    public function heroImageUrl(): string
    {
        return $this->heroImage()?->url() ?? asset('images/placeholder-property.svg');
    }

    public function priceDisplay(): string
    {
        if ($this->status === 'sold') {
            return $this->sold_price ? 'Sold for ' . Format::price($this->sold_price) : 'Sold';
        }

        if (filled($this->price_label)) {
            return $this->price_label;
        }

        return $this->price ? Format::price($this->price) : 'Contact for price';
    }

    /**
     * The rental price line, or null when the property is not on the rental
     * market. A label override wins; otherwise the monthly (or yearly) figure.
     */
    public function rentDisplay(): ?string
    {
        if (! $this->is_for_rent) {
            return null;
        }

        if (filled($this->rent_price_label)) {
            return $this->rent_price_label;
        }

        if (! $this->rent_price) {
            return 'Rent on application';
        }

        return Format::price($this->rent_price) . ' / ' . ($this->rent_period === 'year' ? 'year' : 'month');
    }

    /** Land size in the local unit - Marla/Kanal in Pakistan, m² elsewhere. */
    public function landDisplay(): ?string
    {
        return Format::area($this->land_size);
    }

    /** Covered area is quoted in square feet locally. */
    public function floorDisplay(): ?string
    {
        return $this->floor_size ? number_format($this->floor_size) . ' sq ft' : null;
    }

    /**
     * "House 88, Sector C" + area "Bahria Town" -> "House 88, Sector C, Bahria Town".
     * The area is left off when the street address already names it, so we never
     * print "Sector C, Bahria Town, Bahria Town".
     */
    public function shortAddress(): string
    {
        $lead = Str::before(Str::slug((string) $this->suburb), '-');

        return ($lead && Str::contains(Str::slug($this->address), $lead))
            ? $this->address
            : $this->address . ', ' . $this->suburb;
    }

    public function fullAddress(): string
    {
        // Not every market has postal codes - Dubai does not - so the tail is
        // only appended when there is something to append.
        $tail = trim($this->state . ' ' . $this->postcode);

        return $tail === '' ? $this->shortAddress() : $this->shortAddress() . ', ' . $tail;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'for_sale'    => 'For Sale',
            'under_offer' => 'Under Offer',
            'sold'        => 'Sold',
            'rental'      => 'To Rent',
            default       => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function rentStatusLabel(): string
    {
        return match ($this->rent_status) {
            'available'  => 'To Let',
            'let_agreed' => 'Let Agreed',
            'let'        => 'Let',
            default      => ucfirst(str_replace('_', ' ', (string) $this->rent_status)),
        };
    }

    /** Land has no rooms to count, so the bed/bath/car row is hidden for it. */
    public function hasRooms(): bool
    {
        return $this->type !== 'land'
            && ($this->bedrooms || $this->bathrooms || $this->carspaces);
    }

    public function typeLabel(): string
    {
        return config('agent.property_types.' . $this->type, ucfirst($this->type));
    }

    /**
     * Landmarks grouped by kind, in the order the page presents them. Unknown
     * kinds still render, they just sort after the familiar ones - so a new
     * landmark type needs no view change to appear.
     *
     * @return array<string, list<array{type?: string, name?: string, distance?: string}>>
     */
    public function nearbyByType(): array
    {
        $groups = [];

        foreach ((array) $this->nearby as $place) {
            if (filled($place['name'] ?? null)) {
                $groups[$place['type'] ?? 'other'][] = $place;
            }
        }

        $ordered = [];

        foreach (['metro', 'mall', 'school', 'hospital', 'beach', 'park', 'golf', 'airport'] as $type) {
            if ($groups[$type] ?? null) {
                $ordered[$type] = $groups[$type];
                unset($groups[$type]);
            }
        }

        return $ordered + $groups;
    }

    /** The nearest metro, or null - the one landmark buyers screen on first. */
    public function nearestMetro(): ?array
    {
        foreach ((array) $this->nearby as $place) {
            if (($place['type'] ?? null) === 'metro' && filled($place['name'] ?? null)) {
                return $place;
            }
        }

        return null;
    }

    public function nearbyTypeLabel(string $type): string
    {
        return match ($type) {
            'metro'    => 'Metro & transport',
            'mall'     => 'Shopping',
            'school'   => 'Schools',
            'hospital' => 'Healthcare',
            'beach'    => 'Beach',
            'park'     => 'Parks & open space',
            'golf'     => 'Golf',
            'airport'  => 'Airport',
            default    => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function furnishingLabel(): ?string
    {
        return match ($this->furnishing) {
            'furnished'      => 'Fully furnished',
            'semi_furnished' => 'Semi-furnished',
            'unfurnished'    => 'Unfurnished',
            default          => filled($this->furnishing) ? ucfirst(str_replace('_', ' ', $this->furnishing)) : null,
        };
    }

    public function completionLabel(): ?string
    {
        return match ($this->completion) {
            'ready'    => 'Ready',
            'off_plan' => 'Off-plan',
            default    => filled($this->completion) ? ucfirst(str_replace('_', ' ', $this->completion)) : null,
        };
    }

    public function ownershipLabel(): ?string
    {
        return match ($this->ownership) {
            'freehold'  => 'Freehold',
            'leasehold' => 'Leasehold',
            default     => filled($this->ownership) ? ucfirst($this->ownership) : null,
        };
    }

    /** Quoted the way Dubai quotes it: AED per square foot of built-up area, per year. */
    public function serviceChargeDisplay(): ?string
    {
        if (! $this->service_charge) {
            return null;
        }

        return config('agent.currency', 'AED') . ' ' . number_format($this->service_charge) . ' per sq ft / year';
    }

    /**
     * The listing-details rows, already filtered: anything the owner did not
     * supply is absent rather than printed as an em dash.
     *
     * @return array<string, string>
     */
    public function detailRows(): array
    {
        $rows = [
            'Property type' => $this->typeLabel(),
            'Completion'    => $this->completionLabel(),
            'Handover'      => $this->handover,
            'Furnishing'    => $this->furnishingLabel(),
            'View'          => $this->view_type,
            'Ownership'     => $this->ownershipLabel(),
            'Service charge' => $this->serviceChargeDisplay(),
            'Payment terms' => $this->payment_terms,
            'Permit number' => $this->permit_number,
        ];

        return array_filter($rows, fn($value) => filled($value));
    }
}
