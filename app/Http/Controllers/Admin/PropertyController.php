<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $properties = Property::with('images')
            ->when($request->input('q'), fn ($q, $v) => $q
                ->where(fn ($sub) => $sub->where('title', 'like', "%{$v}%")->orWhere('suburb', 'like', "%{$v}%")))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.properties.index', compact('properties'));
    }

    public function create()
    {
        return view('admin.properties.form', [
            'property' => new Property(['status' => 'for_sale', 'type' => 'house', 'state' => config('agent.office.state'), 'is_published' => true]),
        ]);
    }

    public function store(StorePropertyRequest $request)
    {
        $property = Property::create($request->propertyAttributes());

        $this->syncImages($request, $property);

        return redirect()
            ->route('admin.properties.edit', $property)
            ->with('status', 'Property created.');
    }

    public function edit(Property $property)
    {
        $property->load('images');

        return view('admin.properties.form', compact('property'));
    }

    public function update(StorePropertyRequest $request, Property $property)
    {
        $property->update($request->propertyAttributes());

        $this->syncImages($request, $property);

        return redirect()
            ->route('admin.properties.edit', $property)
            ->with('status', 'Property updated.');
    }

    public function destroy(Property $property)
    {
        foreach ($property->images as $image) {
            if ($image->isUpload()) {
                Storage::disk('public')->delete($image->path);
            }
        }

        $property->delete();

        return redirect()
            ->route('admin.properties.index')
            ->with('status', 'Property deleted.');
    }

    public function destroyImage(Property $property, PropertyImage $image)
    {
        abort_unless($image->property_id === $property->id, 404);

        if ($image->isUpload()) {
            Storage::disk('public')->delete($image->path);
        }

        $image->delete();

        return back()->with('status', 'Image removed.');
    }

    private function syncImages(StorePropertyRequest $request, Property $property): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $highest = $property->images()->max('sort_order');
        $next = $highest === null ? 0 : (int) $highest + 1;

        foreach ($request->file('images') as $file) {
            $property->images()->create([
                'path'       => $file->store("properties/{$property->id}", 'public'),
                'alt'        => $property->title,
                'sort_order' => $next++,
            ]);
        }
    }
}
