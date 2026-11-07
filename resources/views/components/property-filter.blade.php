{{--
    Server-owned listing filter.

    A GET form, printed complete in the first paint: the search box, the Filters
    disclosure and every select carry their current values, so filtering works
    with JavaScript off and the block never changes height when anything mounts.
    The "Filters" panel is a native checkbox + label (no JavaScript); it starts
    open when filters are already applied, and the badge counts them here rather
    than in the browser.

    Usage:
        <x-property-filter :action="route('for-sale')" :suburbs="$suburbs" :filters="$filters" />
--}}
@props([
'action',
'suburbs' => [],
'filters' => [],
'types' => [],
'priceBands' => [],
'priceLabel' => 'Price',
'rent' => false,
'showPrice' => true,
'showSort' => true,
])

@php
$minKey = $rent ? 'rent_min' : 'min';
$maxKey = $rent ? 'rent_max' : 'max';
$value = fn (string $key) => (string) ($filters[$key] ?? '');

$active = collect($filters)
->except(['q', 'sort'])
->filter(fn ($v) => $v !== null && $v !== '')
->count();

$sorts = ['' => 'Newest first'];
$sorts += $rent
? ['rent_asc' => 'Rent: low to high', 'rent_desc' => 'Rent: high to low']
: ['price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'];
$sorts += ['beds_desc' => 'Most bedrooms'];
@endphp

<form action="{{ $action }}" method="GET" class="rounded-card border border-ink-100 bg-white p-4 sm:p-5">
    <input type="checkbox" id="filter-more" class="peer sr-only" @checked($active> 0)>

    <div class="flex flex-col gap-3 sm:flex-row">
        <div class="relative flex-1">
            <svg class="pointer-events-none absolute top-1/2 left-3.5 h-4.5 w-4.5 -translate-y-1/2 text-ink-400"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="7" />
                <path stroke-linecap="round" d="m20 20-3.5-3.5" />
            </svg>
            <label class="sr-only" for="filter-q">Search by area or block</label>
            <input
                id="filter-q"
                name="q"
                value="{{ $value('q') }}"
                type="search"
                class="input pl-10"
                placeholder="Search area, block or property">
        </div>

        <label
            for="filter-more"
            class="btn-outline shrink-0 cursor-pointer peer-focus-visible:ring-2 peer-focus-visible:ring-brass-500 peer-focus-visible:ring-offset-2 sm:w-auto">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="M4 6h16M7 12h10M10 18h4" />
            </svg>
            Filters
            @if ($active)
            <span class="ml-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-brass-500 px-1.5 text-xs font-bold text-white">{{ $active }}</span>
            @endif
        </label>

        <button type="submit" class="btn-primary shrink-0 sm:w-auto">Search</button>
    </div>

    <div class="mt-4 hidden border-t border-ink-100 pt-4 peer-checked:block">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label" for="filter-suburb">Area</label>
                <select id="filter-suburb" name="suburb" class="input">
                    <option value="">All areas</option>
                    @foreach ($suburbs as $suburb)
                    <option value="{{ $suburb }}" @selected($value('suburb')===(string) $suburb)>{{ $suburb }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-type">Property type</label>
                <select id="filter-type" name="type" class="input">
                    <option value="">Any type</option>
                    @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected($value('type')===(string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-beds">Bedrooms (min)</label>
                <select id="filter-beds" name="beds" class="input">
                    <option value="">Any</option>
                    @foreach (range(1, 5) as $n)
                    <option value="{{ $n }}" @selected($value('beds')===(string) $n)>{{ $n }}+</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-baths">Bathrooms (min)</label>
                <select id="filter-baths" name="baths" class="input">
                    <option value="">Any</option>
                    @foreach (range(1, 4) as $n)
                    <option value="{{ $n }}" @selected($value('baths')===(string) $n)>{{ $n }}+</option>
                    @endforeach
                </select>
            </div>

            @if ($showPrice)
            <div>
                <label class="label" for="filter-{{ $minKey }}">{{ $priceLabel }} from</label>
                <select id="filter-{{ $minKey }}" name="{{ $minKey }}" class="input">
                    @foreach ($priceBands as $band => $label)
                    <option value="{{ $band }}" @selected($value($minKey)===(string) $band)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label" for="filter-{{ $maxKey }}">{{ $priceLabel }} to</label>
                <select id="filter-{{ $maxKey }}" name="{{ $maxKey }}" class="input">
                    @foreach ($priceBands as $band => $label)
                    <option value="{{ $band }}" @selected($value($maxKey)===(string) $band)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            @if ($showSort)
            <div>
                <label class="label" for="filter-sort">Sort by</label>
                <select id="filter-sort" name="sort" class="input">
                    @foreach ($sorts as $key => $label)
                    <option value="{{ $key }}" @selected($value('sort')===(string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>

        <div class="mt-4 flex items-center gap-4">
            <button type="submit" class="btn-primary">Apply filters</button>
            <a href="{{ $action }}" class="text-sm font-semibold text-ink-500 underline hover:text-ink-900">Clear all</a>
        </div>
    </div>
</form>