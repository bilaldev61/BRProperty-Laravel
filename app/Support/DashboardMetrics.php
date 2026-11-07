<?php

namespace App\Support;

use App\Models\Enquiry;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the admin dashboard plots. Kept out of the controller so the
 * queries are testable on their own.
 */
class DashboardMetrics
{
    public const WEEKS = 12;

    /**
     * Headline tiles, each with a period-on-period delta.
     *
     * The money and enquiry tiles are opted into rather than filtered out
     * afterwards, so a role that may not see a figure never has it summed in
     * the first place. A role that may see neither is left with the listing
     * count, and the view lays out however many tiles it is given.
     */
    public function tiles(bool $withValue = true, bool $withEnquiries = true): array
    {
        $tiles = [
            [
                'label'  => 'Listings on the market',
                'value'  => (string) Property::published()->forSale()->count(),
                'meta'   => Property::published()->where('status', 'under_offer')->count() . ' under offer',
                'delta'  => null,
            ],
        ];

        if ($withValue) {
            $activeValue = (int) Property::published()->forSale()->sum('price');
            $soldYear = Property::published()->sold()->where('sold_at', '>=', now()->subYear());

            $tiles[] = [
                'label'  => 'Portfolio value',
                'value'  => Format::price($activeValue),
                'meta'   => 'Asking prices, listings live now',
                'delta'  => null,
            ];

            $tiles[] = [
                'label'  => 'Sold, last 12 months',
                'value'  => Format::price((int) $soldYear->clone()->sum('sold_price')),
                'meta'   => $soldYear->clone()->count() . ' transfers completed',
                'delta'  => null,
            ];
        }

        if ($withEnquiries) {
            $last30 = Enquiry::where('created_at', '>=', now()->subDays(30))->count();
            $prev30 = Enquiry::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count();

            $tiles[] = [
                'label'  => 'Enquiries, last 30 days',
                'value'  => (string) $last30,
                'meta'   => Enquiry::needsReply()->count() . ' still need a reply',
                'delta'  => $this->delta($last30, $prev30),
            ];
        }

        return $tiles;
    }

    /** Weekly enquiry counts, split by type, for a stacked bar chart. */
    public function enquiriesByWeek(): array
    {
        $start = CarbonImmutable::now()->subWeeks(self::WEEKS - 1)->startOfWeek();

        $rows = Enquiry::where('created_at', '>=', $start)
            ->get(['type', 'created_at'])
            ->groupBy(fn(Enquiry $e) => CarbonImmutable::parse($e->created_at)->startOfWeek()->toDateString());

        $series = ['property' => 'Property', 'appraisal' => 'Appraisal', 'contact' => 'General'];
        $points = [];

        for ($i = 0; $i < self::WEEKS; $i++) {
            $week = $start->addWeeks($i);
            $bucket = $rows->get($week->toDateString(), collect());

            $points[] = [
                'label'    => $week->format('j M'),
                'fullLabel' => 'Week of ' . $week->format('j M Y'),
                'values'   => collect($series)->map(
                    fn($_, $key) => $bucket->where('type', $key)->count()
                )->values()->all(),
            ];
        }

        return ['series' => array_values($series), 'points' => $points];
    }

    /** Which live listings are actually pulling enquiries. */
    public function enquiriesByListing(int $limit = 6, bool $withUrls = true): array
    {
        // SQLite rejects HAVING on a non-aggregate query, so the zero-enquiry
        // listings are filtered off after the fact rather than in SQL.
        $properties = Property::published()
            ->withCount(['enquiries' => fn($q) => $q->where('created_at', '>=', now()->subDays(90))])
            ->orderByDesc('enquiries_count')
            ->take($limit)
            ->get()
            ->filter(fn(Property $p) => $p->enquiries_count > 0)
            ->values();

        return $properties->map(fn(Property $p) => [
            'label'    => $p->title,
            'subLabel' => $p->suburb,
            'value'    => $p->enquiries_count,
            // Without properties.manage the row is a label, not a link: the
            // chart drops the anchor rather than sending a reader to a 403.
            'url'      => $withUrls ? route('admin.properties.edit', $p) : null,
        ])->all();
    }

    /** Where the money on the books actually sits. */
    public function valueByArea(): array
    {
        return Property::published()
            ->forSale()
            ->whereNotNull('price')
            ->selectRaw('suburb, SUM(price) as total, COUNT(*) as listings')
            ->groupBy('suburb')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'label'     => $row->suburb,
                'value'     => (int) $row->total,
                'formatted' => Format::price((int) $row->total),
                'subLabel'  => $row->listings . ' ' . str('listing')->plural($row->listings),
            ])->all();
    }

    /** Pipeline counts for the small status strip. */
    public function pipeline(): array
    {
        return [
            ['label' => 'For sale',    'value' => Property::published()->where('status', 'for_sale')->count()],
            ['label' => 'Under offer', 'value' => Property::published()->where('status', 'under_offer')->count()],
            ['label' => 'Sold',        'value' => Property::published()->sold()->count()],
            ['label' => 'Drafts',      'value' => Property::where('is_published', false)->count()],
        ];
    }

    public function recentEnquiries(int $limit = 6): Collection
    {
        return Enquiry::with('property')->latest()->take($limit)->get();
    }

    private function delta(int $current, int $previous): ?array
    {
        if ($previous === 0) {
            return null;
        }

        $change = round((($current - $previous) / $previous) * 100);

        return [
            'value'     => ($change > 0 ? '+' : '') . $change . '%',
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
            'caption'   => 'vs previous 30 days',
        ];
    }
}
