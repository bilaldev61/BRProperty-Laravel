<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'suburb', 'type', 'beds', 'baths', 'min', 'max', 'sort']);

        $properties = Property::published()->forSale()->with('images')
            ->filter($filters)
            ->orderBy(...$this->sortColumns($filters['sort'] ?? null))
            ->paginate(9)
            ->withQueryString();

        return view('properties.index', [
            'properties' => $properties,
            'filters'    => $filters,
            'suburbs'    => $this->suburbs('for_sale'),
        ]);
    }

    public function sold(Request $request)
    {
        $filters = $request->only(['q', 'suburb', 'type', 'beds', 'baths', 'min', 'max', 'sort']);

        $properties = Property::published()->sold()->with('images')
            ->filter($filters)
            ->orderByDesc('sold_at')
            ->paginate(9)
            ->withQueryString();

        return view('properties.sold', [
            'properties' => $properties,
            'filters'    => $filters,
            'suburbs'    => $this->suburbs('sold'),
            'stats'      => [
                'count'      => Property::published()->sold()->count(),
                'avgDays'    => (int) round(Property::published()->sold()->avg('days_on_market') ?? 0),
                'totalValue' => (int) Property::published()->sold()->sum('sold_price'),
            ],
        ]);
    }

    public function rentals(Request $request)
    {
        $filters = $request->only(['q', 'suburb', 'type', 'beds', 'baths', 'rent_min', 'rent_max', 'sort']);

        $properties = Property::published()->forRent()->with('images')
            ->filter($filters)
            ->orderBy(...$this->sortColumns($filters['sort'] ?? null))
            ->paginate(9)
            ->withQueryString();

        return view('properties.rentals', [
            'properties' => $properties,
            'filters'    => $filters,
            'suburbs'    => $this->suburbs('rent'),
        ]);
    }

    public function show(Property $property)
    {
        abort_unless($property->is_published, 404);

        $property->load('images');

        $similar = Property::published()
            ->where('id', '!=', $property->id)
            ->where('status', $property->status)
            ->where('suburb', $property->suburb)
            ->with('images')
            ->take(3)
            ->get();

        if ($similar->count() < 3) {
            $similar = $similar->concat(
                Property::published()
                    ->where('id', '!=', $property->id)
                    ->whereNotIn('id', $similar->pluck('id'))
                    ->where('status', $property->status)
                    ->with('images')
                    ->take(3 - $similar->count())
                    ->get()
            );
        }

        return view('properties.show', compact('property', 'similar'));
    }

    /** @return array{0: string, 1: string} */
    private function sortColumns(?string $sort): array
    {
        return match ($sort) {
            'price_asc'  => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            'rent_asc'   => ['rent_price', 'asc'],
            'rent_desc'  => ['rent_price', 'desc'],
            'beds_desc'  => ['bedrooms', 'desc'],
            default      => ['created_at', 'desc'],
        };
    }

    private function suburbs(string $status)
    {
        $query = Property::published();

        // Each list shows a different slice, so the area dropdown must offer the
        // areas present in that slice - including dual for-sale-and-rent rows on
        // the rentals page, which forRent() already keeps.
        match ($status) {
            'sold'  => $query->sold(),
            'rent'  => $query->forRent(),
            default => $query->forSale(),
        };

        return $query->distinct()
            ->orderBy('suburb')
            ->pluck('suburb');
    }
}
