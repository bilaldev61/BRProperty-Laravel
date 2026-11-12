<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Support\Blog;
use App\Support\CatalogueCache;

class PageController extends Controller
{
    public function home()
    {
        $catalogue = app(CatalogueCache::class);
        $home = $catalogue->home();

        return view('pages.home', [
            'featured'     => $home['featured'],
            'recentlySold' => $home['recentlySold'],
            'testimonials' => $catalogue->featuredTestimonials(),
            'soldCount'    => $home['soldCount'],
        ]);
    }

    public function about()
    {
        return view('pages.about', [
            'testimonials' => app(CatalogueCache::class)->testimonials()->take(2),
        ]);
    }

    public function buying()
    {
        return view('pages.buying', [
            'listings' => Property::published()->forSale()->with('images')->take(3)->get(),
        ]);
    }

    public function selling()
    {
        return view('pages.selling', [
            'recentlySold' => Property::published()->sold()->with('images')
                ->orderByDesc('sold_at')->take(3)->get(),
        ]);
    }

    public function rentals()
    {
        return view('pages.rentals');
    }

    public function management()
    {
        return view('pages.management');
    }

    public function testimonials()
    {
        return view('pages.testimonials', [
            'testimonials' => app(CatalogueCache::class)->testimonials(),
        ]);
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function privacy()
    {
        return view('pages.privacy');
    }

    public function blog()
    {
        return view('pages.blog', [
            'posts' => Blog::latest(),
        ]);
    }

    public function post(string $slug)
    {
        $post = Blog::find($slug);

        abort_if($post === null, 404);

        return view('pages.post', [
            'post'    => $post,
            'latest'  => collect(Blog::latest())
                ->reject(fn($p) => $p['slug'] === $slug)
                ->take(3)
                ->values()
                ->all(),
        ]);
    }
}
