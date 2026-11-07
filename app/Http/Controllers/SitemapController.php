<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $staticRoutes = [
            ['loc' => route('home'),         'priority' => '1.0', 'freq' => 'weekly'],
            ['loc' => route('for-sale'),   'priority' => '0.9', 'freq' => 'daily'],
            ['loc' => route('for-rent'), 'priority' => '0.8', 'freq' => 'daily'],
            ['loc' => route('sold'),         'priority' => '0.7', 'freq' => 'weekly'],
            ['loc' => route('about'),        'priority' => '0.6', 'freq' => 'monthly'],
            ['loc' => route('buying'),       'priority' => '0.6', 'freq' => 'monthly'],
            ['loc' => route('selling'),      'priority' => '0.8', 'freq' => 'monthly'],
            ['loc' => route('rentals'),      'priority' => '0.7', 'freq' => 'weekly'],
            ['loc' => route('management'),   'priority' => '0.6', 'freq' => 'monthly'],
            ['loc' => route('testimonials'), 'priority' => '0.5', 'freq' => 'monthly'],
            ['loc' => route('blog'),         'priority' => '0.6', 'freq' => 'weekly'],
            ['loc' => route('contact'),      'priority' => '0.7', 'freq' => 'monthly'],
        ];

        // Images are declared inline so listing photos are eligible for Google
        // Images, which is where a lot of property search actually starts.
        $properties = Property::published()
            ->with('images')
            ->orderByDesc('updated_at')
            ->get();

        $lastUpdated = $properties->max('updated_at') ?? now();

        return response()
            ->view('sitemap', compact('staticRoutes', 'properties', 'lastUpdated'))
            ->header('Content-Type', 'application/xml');
    }

    public function robots(): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            '',
            'Sitemap: ' . route('sitemap'),
            '',
        ]);

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }
}
