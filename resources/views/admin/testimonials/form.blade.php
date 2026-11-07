@php $editing = $testimonial->exists; @endphp

<x-layouts.admin
    :title="$editing ? 'Edit review' : 'New review'"
    :heading="$editing ? 'Edit review' : 'New review'"
    :intro="$editing
        ? 'Saved changes appear on the site straight away.'
        : 'Paste what the client said in their own words. A review carries more weight than any claim the site makes about itself.'">

    <x-slot:actions>
        <a href="{{ route('admin.testimonials.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-900">&larr;
            All reviews</a>
    </x-slot:actions>

    <form method="POST"
        action="{{ $editing ? route('admin.testimonials.update', $testimonial) : route('admin.testimonials.store') }}"
        class="max-w-2xl">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.card tone="brass">
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="author">Client name</label>
                        <input id="author" name="author" class="input" value="{{ old('author', $testimonial->author) }}"
                            required>
                    </div>
                    <div>
                        <label class="label" for="location">Suburb</label>
                        <input id="location" name="location" class="input" value="{{ old('location', $testimonial->location) }}"
                            placeholder="Dubai Marina">
                    </div>
                    <div>
                        <label class="label" for="role">They were a</label>
                        <select id="role" name="role" class="input">
                            @foreach (['Seller', 'Buyer', 'Landlord', 'Tenant'] as $role)
                            <option value="{{ $role }}" @selected(old('role', $testimonial->role) === $role)>{{ $role }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="rating">Rating</label>
                        <select id="rating" name="rating" class="input">
                            @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" @selected((int) old('rating', $testimonial->rating) === $i)>{{ str_repeat('★', $i) }} ({{ $i }})</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div>
                    <label class="label" for="body">Testimonial</label>
                    <textarea id="body" name="body" rows="6" class="input" required>{{ old('body', $testimonial->body) }}</textarea>
                </div>

                <div>
                    <label class="label" for="sort_order">Sort order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" class="input w-32"
                        value="{{ old('sort_order', $testimonial->sort_order ?? 0) }}">
                    <p class="mt-1 text-xs text-ink-400">Lower numbers appear first.</p>
                </div>

                <div class="space-y-3 border-t border-ink-100 pt-4">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="is_published" value="1"
                            class="mt-0.5 rounded border-ink-300 text-ink-900 focus:ring-brass-500"
                            @checked(old('is_published', $testimonial->is_published ?? true))>
                        <span><span class="font-medium text-ink-900">Published</span><br><span class="text-ink-500">Show on the
                                testimonials page.</span></span>
                    </label>
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="is_featured" value="1"
                            class="mt-0.5 rounded border-ink-300 text-ink-900 focus:ring-brass-500"
                            @checked(old('is_featured', $testimonial->is_featured))>
                        <span><span class="font-medium text-ink-900">Featured</span><br><span class="text-ink-500">Include
                                in the homepage carousel.</span></span>
                    </label>
                </div>
            </div>
        </x-admin.card>

        <button type="submit" class="btn-primary mt-6">{{ $editing ? 'Save changes' : 'Add review' }}</button>
    </form>
</x-layouts.admin>