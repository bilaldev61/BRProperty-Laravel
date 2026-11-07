<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Support\DashboardMetrics;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * The dashboard is the one screen every role lands on, so it is assembled
     * from parts rather than handed over whole. Each part answers to the
     * ability that owns its data: this decides what is worth computing, and
     * the view decides what is worth drawing, against the same names.
     */
    public function __invoke(Request $request, DashboardMetrics $metrics)
    {
        $user = $request->user();

        $seesValue = $user->can('finance.view');
        $seesEnquiries = $user->can('enquiries.view');

        return view('admin.dashboard', [
            'seesValue'        => $seesValue,
            'seesEnquiries'    => $seesEnquiries,
            'canEditListings'  => $user->can('properties.manage'),
            'tiles'            => $metrics->tiles(withValue: $seesValue, withEnquiries: $seesEnquiries),
            'pipeline'         => $metrics->pipeline(),
            'enquiriesByWeek'  => $seesEnquiries ? $metrics->enquiriesByWeek() : null,
            'topListings'      => $seesEnquiries ? $metrics->enquiriesByListing(withUrls: $user->can('properties.manage')) : null,
            'valueByArea'      => $seesValue ? $metrics->valueByArea() : null,
            'recentEnquiries'  => $seesEnquiries ? $metrics->recentEnquiries() : null,
            'latestProperties' => Property::with('images')->latest()->take(4)->get(),
        ]);
    }
}
