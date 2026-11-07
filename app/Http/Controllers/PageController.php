<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Testimonial;
use App\Support\Blog;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home', [
            'featured' => Property::published()->forSale()->with('images')
                ->orderByDesc('is_featured')->orderByDesc('created_at')->take(6)->get(),
            'recentlySold' => Property::published()->sold()->with('images')
                ->orderByDesc('sold_at')->take(3)->get(),
            'testimonials' => Testimonial::published()->where('is_featured', true)->take(3)->get(),
            'soldCount'    => Property::published()->sold()->count(),
        ]);
    }

    public function about()
    {
        return view('pages.about', [
            'testimonials' => Testimonial::published()->take(2)->get(),
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
            'testimonials' => Testimonial::published()->get(),
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
