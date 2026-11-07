<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index()
    {
        return view('admin.testimonials.index', [
            'testimonials' => Testimonial::orderBy('sort_order')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.testimonials.form', [
            'testimonial' => new Testimonial(['rating' => 5, 'is_published' => true, 'role' => 'Seller']),
        ]);
    }

    public function store(Request $request)
    {
        Testimonial::create($this->validated($request));

        return redirect()->route('admin.testimonials.index')->with('status', 'Testimonial added.');
    }

    public function edit(Testimonial $testimonial)
    {
        return view('admin.testimonials.form', compact('testimonial'));
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $testimonial->update($this->validated($request));

        return redirect()->route('admin.testimonials.index')->with('status', 'Testimonial updated.');
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();

        return redirect()->route('admin.testimonials.index')->with('status', 'Testimonial deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'author'     => ['required', 'string', 'max:120'],
            'location'   => ['nullable', 'string', 'max:120'],
            'role'       => ['nullable', 'string', 'max:40'],
            'body'       => ['required', 'string', 'max:2000'],
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['sort_order']   = $data['sort_order'] ?? 0;
        $data['is_featured']  = $request->boolean('is_featured');
        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }
}
