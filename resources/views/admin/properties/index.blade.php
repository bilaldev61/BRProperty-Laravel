<x-layouts.admin title="Properties" heading="Properties"
    intro="Every listing on the site, published or draft. Drafts stay off the public pages until you publish them.">
    <x-slot:actions>
        @can('properties.manage')
        <a href="{{ route('admin.properties.create') }}" class="btn-primary !px-4 !py-2 text-xs">+ New property</a>
        @endcan
    </x-slot:actions>

    <form method="GET"
        class="flex flex-col gap-3 rounded-xl border border-ink-200/70 bg-white p-3 shadow-sm shadow-ink-950/5 sm:flex-row sm:items-center">
        <label class="sr-only" for="admin-q">Search</label>
        <input id="admin-q" name="q" value="{{ request('q') }}" class="input flex-1" placeholder="Search title or area">

        <label class="sr-only" for="admin-status">Status</label>
        <select id="admin-status" name="status" class="input sm:w-48">
            <option value="">All statuses</option>
            @foreach (['for_sale' => 'For Sale', 'under_offer' => 'Under Offer', 'sold' => 'Sold'] as $value => $label)
            <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="btn-primary sm:w-auto">Filter</button>
    </form>

    @if ($properties->isEmpty())
    <x-empty-state title="No properties yet" message="Add your first listing to get the site populated."
        :action-url="route('admin.properties.create')" action-label="Add a property" />
    @else
    <x-admin.card :padded="false" tone="teal">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[52rem] text-left text-sm">
                <thead class="border-b border-ink-100 bg-sand-50 text-[11px] tracking-[0.12em] text-ink-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Property</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Price</th>
                        <th class="px-5 py-3 font-semibold">Beds</th>
                        <th class="px-5 py-3 font-semibold">Live</th>
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-50">
                    @foreach ($properties as $property)
                    <tr class="hover:bg-sand-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $property->heroImageUrl() }}" alt="" loading="lazy" decoding="async"
                                    class="h-11 w-14 shrink-0 rounded-md object-cover">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-ink-900">{{ $property->title }}</p>
                                    <p class="text-xs text-ink-500">{{ $property->shortAddress() }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <x-status-chip :status="$property->status" />
                        </td>
                        <td class="px-5 py-3 text-ink-700">{{ $property->priceDisplay() }}</td>
                        <td class="px-5 py-3 text-ink-700">{{ $property->bedrooms }}</td>
                        <td class="px-5 py-3">
                            @if ($property->is_published)
                            <x-admin.badge tone="green">Live</x-admin.badge>
                            @else
                            <x-admin.badge>Draft</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('properties.show', $property) }}" target="_blank"
                                class="text-ink-500 hover:text-ink-900">View</a>

                            @can('properties.manage')
                            <a href="{{ route('admin.properties.edit', $property) }}"
                                class="ml-3 font-medium text-brass-700 hover:underline">Edit</a>

                            <form method="POST" action="{{ route('admin.properties.destroy', $property) }}" class="ml-3 inline"
                                data-confirm="{{ $property->title }} will be removed along with its photos. This cannot be undone."
                                data-confirm-title="Delete this listing?">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div>{{ $properties->links() }}</div>
    @endif
</x-layouts.admin>