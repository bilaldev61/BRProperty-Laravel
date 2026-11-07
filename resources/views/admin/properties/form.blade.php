@php $editing = $property->exists; @endphp

<x-layouts.admin
    :title="$editing ? 'Edit property' : 'New property'"
    :heading="$editing ? 'Edit property' : 'New property'"
    :intro="$editing
        ? 'Everything the public listing page shows comes from this form. Saving publishes the changes immediately.'
        : 'Start with the headline, address and price. Photos and the rest can follow in a second pass.'">

    <x-slot:actions>
        <a href="{{ route('admin.properties.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-900">&larr; All properties</a>
    </x-slot:actions>

    <form method="POST"
        action="{{ $editing ? route('admin.properties.update', $property) : route('admin.properties.store') }}"
        enctype="multipart/form-data"
        class="grid gap-6 lg:grid-cols-[1.6fr_1fr] lg:items-start">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6">
            <x-admin.card title="Listing details">
                <div class="space-y-4">
                    <div>
                        <label class="label" for="title">Headline</label>
                        <input id="title" name="title" class="input" value="{{ old('title', $property->title) }}" required
                            placeholder="Renovated 4-bed townhouse in Dubai Hills">
                    </div>

                    <div>
                        <label class="label" for="description">Description</label>
                        <textarea id="description" name="description" rows="10" class="input" required
                            placeholder="Leave a blank line between paragraphs.">{{ old('description', $property->description) }}</textarea>
                        <p class="mt-1 text-xs text-ink-400">Blank lines become separate paragraphs on the listing page.</p>
                    </div>

                    <div>
                        <label class="label" for="features">Features</label>
                        <input id="features" name="features" class="input"
                            value="{{ old('features', is_array($property->features) ? implode(', ', $property->features) : '') }}"
                            placeholder="Solar panels, New roof, Clear title">
                        <p class="mt-1 text-xs text-ink-400">Separate each feature with a comma.</p>
                    </div>

                    <div>
                        <label class="label" for="inspection_times">Inspection times</label>
                        <input id="inspection_times" name="inspection_times" class="input"
                            value="{{ old('inspection_times', $property->inspection_times) }}"
                            placeholder="Saturday and Sunday, 12:00pm - 4:00pm">
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="Address">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label" for="address">Street address</label>
                        <input id="address" name="address" class="input" value="{{ old('address', $property->address) }}" required>
                    </div>
                    <div>
                        <label class="label" for="suburb">Suburb</label>
                        <input id="suburb" name="suburb" class="input" value="{{ old('suburb', $property->suburb) }}" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label" for="state">State</label>
                            <input id="state" name="state" class="input" value="{{ old('state', $property->state ?? config('agent.office.state')) }}" required>
                        </div>
                        <div>
                            <label class="label" for="postcode">Postcode <span class="font-normal text-ink-400">(optional)</span></label>
                            <input id="postcode" name="postcode" class="input" value="{{ old('postcode', $property->postcode) }}">
                        </div>
                    </div>
                    <div>
                        <label class="label" for="latitude">Latitude <span class="font-normal text-ink-400">(optional)</span></label>
                        <input id="latitude" name="latitude" class="input" value="{{ old('latitude', $property->latitude) }}" placeholder="30.2659">
                    </div>
                    <div>
                        <label class="label" for="longitude">Longitude <span class="font-normal text-ink-400">(optional)</span></label>
                        <input id="longitude" name="longitude" class="input" value="{{ old('longitude', $property->longitude) }}" placeholder="-97.7454">
                    </div>
                </div>
                <p class="mt-2 text-xs text-ink-400">Adding coordinates shows a map on the listing page.</p>
            </x-admin.card>

            <x-admin.card title="Photos">
                @if ($editing && $property->images->isNotEmpty())
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                    @foreach ($property->images as $image)
                    <div class="group relative overflow-hidden rounded-lg border border-ink-100">
                        <img src="{{ $image->url() }}" alt="" loading="lazy" decoding="async" class="aspect-4/3 w-full object-cover">
                        @if ($loop->first)
                        <span class="absolute top-1.5 left-1.5 rounded bg-ink-900/85 px-1.5 py-0.5 text-[10px] font-bold text-white uppercase">Cover</span>
                        @endif
                        <button type="button"
                            class="absolute top-1.5 right-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white opacity-0 transition group-hover:opacity-100 focus:opacity-100"
                            aria-label="Remove image"
                            onclick="document.getElementById('del-img-{{ $image->id }}').requestSubmit()">&times;</button>
                    </div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-ink-400">The first image is used as the cover everywhere on the site.</p>
                @endif

                <div class="mt-5">
                    <label class="label" for="images">Add photos</label>
                    <input id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple
                        class="w-full rounded-lg border border-dashed border-ink-300 bg-sand-50 px-4 py-6 text-sm file:mr-4 file:rounded-full file:border-0 file:bg-ink-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-sand-50">
                    <p class="mt-1 text-xs text-ink-400">JPG, PNG or WebP. Up to 20 images, 5MB each.</p>
                </div>
            </x-admin.card>
        </div>

        <div class="space-y-6 lg:sticky lg:top-6">
            <x-admin.card title="Status & price" tone="brass">
                <div class="space-y-4">
                    <div>
                        <label class="label" for="status">Status</label>
                        <select id="status" name="status" class="input">
                            @foreach (['for_sale' => 'For Sale', 'under_offer' => 'Under Offer', 'sold' => 'Sold', 'rental' => 'Rental (rent only)'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $property->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label" for="type">Property type</label>
                        <select id="type" name="type" class="input">
                            @foreach (config('agent.property_types') as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $property->type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label" for="price">Asking price</label>
                        <input id="price" name="price" type="number" min="0" class="input" value="{{ old('price', $property->price) }}" placeholder="625000">
                    </div>

                    <div>
                        <label class="label" for="price_label">Price display override</label>
                        <input id="price_label" name="price_label" class="input" value="{{ old('price_label', $property->price_label) }}"
                            placeholder="Price on application">
                        <p class="mt-1 text-xs text-ink-400">Shown instead of the number. Leave blank to show the price.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-t border-ink-100 pt-4">
                        <div>
                            <label class="label" for="sold_price">Sold price</label>
                            <input id="sold_price" name="sold_price" type="number" min="0" class="input" value="{{ old('sold_price', $property->sold_price) }}">
                        </div>
                        <div>
                            <label class="label" for="sold_at">Sold date</label>
                            <input id="sold_at" name="sold_at" type="date" class="input"
                                value="{{ old('sold_at', $property->sold_at?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-span-2">
                            <label class="label" for="days_on_market">Days on market</label>
                            <input id="days_on_market" name="days_on_market" type="number" min="0" class="input" value="{{ old('days_on_market', $property->days_on_market) }}">
                        </div>
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="To rent"
                description="Independent of the sale side above. A property can be for sale and for rent at the same time.">
                <div class="space-y-4">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="is_for_rent" value="1" class="mt-0.5 rounded border-ink-300 text-ink-900 focus:ring-brass-500"
                            @checked(old('is_for_rent', $property->is_for_rent))>
                        <span><span class="font-medium text-ink-900">Also to rent</span><br><span class="text-ink-500">List this property on the rentals page.</span></span>
                    </label>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="label" for="rent_price">Monthly rent (USD)</label>
                            <input id="rent_price" name="rent_price" type="number" min="0" class="input" value="{{ old('rent_price', $property->rent_price) }}" placeholder="2600">
                        </div>
                        <div>
                            <label class="label" for="rent_period">Billed</label>
                            <select id="rent_period" name="rent_period" class="input">
                                @foreach (['month' => 'Per month', 'year' => 'Per year'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('rent_period', $property->rent_period ?? 'month') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="rent_status">Rental status</label>
                            <select id="rent_status" name="rent_status" class="input">
                                @foreach (['available' => 'Available to let', 'let_agreed' => 'Let agreed', 'let' => 'Let (tenanted)'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('rent_status', $property->rent_status ?? 'available') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="rent_price_label">Rent display override</label>
                            <input id="rent_price_label" name="rent_price_label" class="input" value="{{ old('rent_price_label', $property->rent_price_label) }}"
                                placeholder="Rent negotiable">
                        </div>
                    </div>
                    <p class="text-xs text-ink-400">Leave "Also to rent" unchecked to keep this a sale-only listing.</p>
                </div>
            </x-admin.card>

            <x-admin.card title="Size">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="label" for="bedrooms">Bedrooms</label>
                        <input id="bedrooms" name="bedrooms" type="number" min="0" class="input" value="{{ old('bedrooms', $property->bedrooms ?? 0) }}" required>
                    </div>
                    <div>
                        <label class="label" for="bathrooms">Bathrooms</label>
                        <input id="bathrooms" name="bathrooms" type="number" min="0" class="input" value="{{ old('bathrooms', $property->bathrooms ?? 0) }}" required>
                    </div>
                    <div>
                        <label class="label" for="carspaces">Car spaces</label>
                        <input id="carspaces" name="carspaces" type="number" min="0" class="input" value="{{ old('carspaces', $property->carspaces ?? 0) }}" required>
                    </div>
                    <div>
                        <label class="label" for="land_size">Land ({{ \App\Support\Format::areaUnitLabel() }})</label>
                        <input id="land_size" name="land_size" type="number" min="0" class="input" value="{{ old('land_size', $property->land_size) }}">
                    </div>
                    <div class="col-span-2">
                        <label class="label" for="floor_size">Covered area (sq ft)</label>
                        <input id="floor_size" name="floor_size" type="number" min="0" class="input" value="{{ old('floor_size', $property->floor_size) }}">
                    </div>
                </div>
            </x-admin.card>

            <x-admin.card title="Visibility & SEO">
                <div class="space-y-4">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="is_published" value="1" class="mt-0.5 rounded border-ink-300 text-ink-900 focus:ring-brass-500"
                            @checked(old('is_published', $property->is_published ?? true))>
                        <span><span class="font-medium text-ink-900">Published</span><br><span class="text-ink-500">Visible on the public site.</span></span>
                    </label>

                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="is_featured" value="1" class="mt-0.5 rounded border-ink-300 text-ink-900 focus:ring-brass-500"
                            @checked(old('is_featured', $property->is_featured))>
                        <span><span class="font-medium text-ink-900">Featured</span><br><span class="text-ink-500">Pushed to the top of the homepage.</span></span>
                    </label>

                    <div>
                        <label class="label" for="meta_description">Meta description</label>
                        <textarea id="meta_description" name="meta_description" rows="3" class="input" maxlength="180"
                            placeholder="Falls back to the first 155 characters of the description.">{{ old('meta_description', $property->meta_description) }}</textarea>
                    </div>
                </div>
            </x-admin.card>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary flex-1">{{ $editing ? 'Save changes' : 'Create property' }}</button>
                @if ($editing)
                <a href="{{ route('properties.show', $property) }}" target="_blank" class="btn-outline">Preview</a>
                @endif
            </div>
        </div>
    </form>

    {{-- Image deletion forms live outside the main form so they never nest --}}
    @if ($editing)
    @foreach ($property->images as $image)
    <form id="del-img-{{ $image->id }}" method="POST"
        action="{{ route('admin.properties.images.destroy', [$property, $image]) }}" class="hidden"
        data-confirm="This photo will be removed from the listing and deleted from storage."
        data-confirm-title="Remove this photo?"
        data-confirm-button="Yes, remove it">
        @csrf @method('DELETE')
    </form>
    @endforeach
    @endif
</x-layouts.admin>