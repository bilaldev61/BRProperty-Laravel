<?php

namespace App\Services\Leads;

use App\Models\Property;
use App\Services\Listings\PropertySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Listings that fit what a lead asked for.
 *
 * First a strict pass: their type, their areas, their budget (with a little
 * headroom), their minimum size and rooms. If nothing fits, one looser pass -
 * same type and areas, more budget room, size and rooms dropped - and every
 * result is marked as a close match rather than presented as what they asked
 * for. Each result says, in words, how it fits.
 */
class PropertyMatcher
{
    /** Over-budget headroom on the strict pass, and on the loose one. */
    private const STRICT_STRETCH = 0.10;

    private const LOOSE_STRETCH = 0.25;

    /**
     * @param  array<string, mixed>  $requirements  from Requirements::fromAnalysis()
     * @return array{exact: bool, properties: list<array<string, mixed>>}
     */
    public function match(array $requirements, int $limit = 5): array
    {
        // Sellers and renters are not shopping the for-sale list.
        if (in_array($requirements['intent'] ?? null, ['sell', 'rent'], true)) {
            return ['exact' => false, 'properties' => []];
        }

        $strict = $this->query($requirements, self::STRICT_STRETCH, loose: false)->get();

        if ($strict->isNotEmpty()) {
            return ['exact' => true, 'properties' => $this->present($strict, $requirements, $limit, close: false)];
        }

        $loose = $this->query($requirements, self::LOOSE_STRETCH, loose: true)->get();

        return ['exact' => false, 'properties' => $this->present($loose, $requirements, $limit, close: true)];
    }

    private function query(array $r, float $stretch, bool $loose): Builder
    {
        $areas = array_filter((array) ($r['areas'] ?? []));

        return Property::published()->forSale()
            ->when($r['property_types'] ?? [], fn ($q, $types) => $q->whereIn('type', $types))
            ->when($areas, fn ($q) => $q->where(function ($q) use ($areas) {
                foreach ($areas as $area) {
                    $q->orWhere('suburb', 'like', '%'.PropertySearch::stripWildcards($area).'%')
                        ->orWhere('address', 'like', '%'.PropertySearch::stripWildcards($area).'%');
                }
            }))
            ->when($r['budget_max'] ?? null, fn ($q, $max) => $q->where('price', '<=', (int) round($max * (1 + $stretch))))
            ->when(! $loose && ($r['budget_min'] ?? null), fn ($q) => $q->where('price', '>=', (int) round($r['budget_min'] * 0.8)))
            ->when(! $loose && ($r['bedrooms_min'] ?? null), fn ($q) => $q->where('bedrooms', '>=', $r['bedrooms_min']))
            ->when(! $loose && ($r['land_marla_min'] ?? null), fn ($q) => $q->where('land_size', '>=', $r['land_marla_min']));
    }

    /** @return list<array<string, mixed>> best first, each with a plain-words fit */
    private function present(Collection $properties, array $r, int $limit, bool $close): array
    {
        return $properties
            ->sortByDesc(fn (Property $p) => $this->score($p, $r))
            ->take($limit)
            ->map(fn (Property $p) => PropertySearch::summary($p) + [
                'fit'   => $this->fit($p, $r),
                'close' => $close,
            ])
            ->values()
            ->all();
    }

    /** Ranking only: closest to budget, then most of what they asked for, then featured. */
    private function score(Property $p, array $r): float
    {
        $score = $p->is_featured ? 1 : 0;

        if (($max = $r['budget_max'] ?? null) && $p->price) {
            $score += $p->price <= $max ? 5 - abs($max - $p->price) / $max : 2;
        }

        if (($beds = $r['bedrooms_min'] ?? null) && $p->bedrooms >= $beds) {
            $score += 2;
        }

        return $score;
    }

    private function fit(Property $p, array $r): string
    {
        $notes = [];

        if (($max = $r['budget_max'] ?? null) && $p->price) {
            $notes[] = $p->price <= $max ? 'within budget' : 'slightly over budget';
        }

        if (($beds = $r['bedrooms_min'] ?? null) && $p->hasRooms()) {
            $notes[] = match (true) {
                $p->bedrooms > $beds  => ($p->bedrooms - $beds).' more bedroom'.($p->bedrooms - $beds > 1 ? 's' : '').' than asked',
                $p->bedrooms === $beds => 'bedrooms as asked',
                default                => 'fewer bedrooms than asked',
            };
        }

        if (($marla = $r['land_marla_min'] ?? null) && $p->land_size) {
            $notes[] = $p->land_size >= $marla ? 'size as asked or larger' : 'smaller than asked';
        }

        return $notes ? implode(', ', $notes) : 'matches the type and area';
    }
}
