@props(['property', 'eager' => false, 'context' => 'sale'])

@php
// The thin bar across the top of the card announces its state at a glance:
// teal while it is open, brass once an offer is on the table, indigo to rent.
$accent = $context === 'rent'
? 'before:bg-indigo-500'
: match ($property->status) {
'for_sale' => 'before:bg-teal-500',
'under_offer' => 'before:bg-brass-500',
'rental' => 'before:bg-indigo-500',
default => 'before:bg-ink-700',
};

$isSold = $property->status === 'sold';
@endphp

<article @class([ 'card group relative flex flex-col transition duration-200 motion-reduce:transition-none' , 'hover:-translate-y-0.5 motion-reduce:hover:translate-y-0' , 'hover:shadow-xl hover:shadow-ink-950/10' , 'before:absolute before:inset-x-0 before:top-0 before:z-10 before:h-1' ,
    $accent,
    ])>
    <a href="{{ route('properties.show', $property) }}" class="relative block overflow-hidden bg-ink-100">
        <img
            src="{{ $property->heroImageUrl() }}"
            alt="{{ $property->title }}, {{ $property->suburb }}"
            class="aspect-4/3 w-full object-cover transition-transform duration-500 motion-reduce:transition-none group-hover:scale-105 motion-reduce:group-hover:scale-100"
            loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async"
            width="800" height="600">

        @if ($context === 'rent')
        <x-rent-chip :property="$property" class="absolute top-3 left-3 shadow-sm" />
        @else
        <x-status-chip :status="$property->status" class="absolute top-3 left-3 shadow-sm" />
        @endif

        @if ($property->is_featured && ! $isSold)
        <span class="chip absolute top-3 right-3 bg-clay-600 text-white shadow-sm">
            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9 4.8 17.6l1-5.8L1.5 7.7l5.9-.9L10 1.5z" />
            </svg>
            Featured
        </span>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-5">
        @if ($context === 'rent')
        <p class="text-xl font-semibold text-indigo-700">{{ $property->rentDisplay() }}</p>
        @else
        <p @class([ 'text-xl font-semibold' , 'text-ink-900'=> ! $isSold,
            'text-ink-500' => $isSold,
            ])>{{ $property->priceDisplay() }}</p>
        @if ($property->is_for_rent && $property->rentDisplay())
        {{-- Sale-or-let homes carry both figures; the rent one reads as a tag
             so the eye keeps the sale price as the headline. --}}
        <p class="mt-1.5 w-fit rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700">
            or rent {{ $property->rentDisplay() }}
        </p>
        @endif
        @endif

        <h3 class="mt-1.5 font-sans text-base font-semibold">
            <a href="{{ route('properties.show', $property) }}"
                class="transition-colors group-hover:text-brass-600">{{ $property->title }}</a>
        </h3>

        <p class="mt-1 mb-4 text-sm text-ink-500">{{ $property->shortAddress() }}</p>

        <dl class="mt-auto flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-ink-100 pt-4 text-sm text-ink-600">
            @if ($property->hasRooms())
            <div class="flex items-center gap-1.5">
                <x-feature-icon name="bed" class="h-4.5 w-4.5 text-ink-400" />
                <dt class="sr-only">Bedrooms</dt>
                <dd>{{ $property->bedrooms === 0 ? 'Studio' : $property->bedrooms }}</dd>
            </div>
            <div class="flex items-center gap-1.5">
                <x-feature-icon name="bath" class="h-4.5 w-4.5 text-ink-400" />
                <dt class="sr-only">Bathrooms</dt>
                <dd>{{ $property->bathrooms }}</dd>
            </div>
            <div class="flex items-center gap-1.5">
                <x-feature-icon name="car" class="h-4.5 w-4.5 text-ink-400" />
                <dt class="sr-only">Car spaces</dt>
                <dd>{{ $property->carspaces }}</dd>
            </div>
            @else
            <div class="flex items-center gap-1.5">
                <dt class="sr-only">Property type</dt>
                <dd>{{ $property->typeLabel() }}</dd>
            </div>
            @endif
            @if ($property->land_size)
            <div class="flex items-center gap-1.5">
                <x-feature-icon name="land" class="h-4.5 w-4.5 text-ink-400" />
                <dt class="sr-only">Land size</dt>
                <dd>{{ $property->landDisplay() }}</dd>
            </div>
            @endif
            @if ($property->floor_size)
            <div class="flex items-center gap-1.5">
                <x-feature-icon name="area" class="h-4.5 w-4.5 text-ink-400" />
                <dt class="sr-only">Covered area</dt>
                <dd>{{ $property->floorDisplay() }}</dd>
            </div>
            @endif
        </dl>

        {{-- Bottom row: the sale record on the left, and a quiet pointer that
             only moves on hover - the card itself is the link, so this is
             decorative and hidden from assistive tech. --}}
        <div class="mt-4 flex items-center justify-between gap-3">
            @if ($isSold && $property->sold_at)
            <p class="text-xs font-medium text-ink-400">
                Sold {{ $property->sold_at->format('j M Y') }}@if ($property->days_on_market) &middot; {{ $property->days_on_market }} days on market @endif
            </p>
            @else
            <span aria-hidden="true"></span>
            @endif

            <span
                class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-brass-600 transition-transform duration-200 motion-reduce:transition-none group-hover:translate-x-0.5"
                aria-hidden="true">
                View details
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.69L10.7 5.6a.75.75 0 1 1 1.1-1.04l4.5 5a.75.75 0 0 1 0 1.04l-4.5 5a.75.75 0 1 1-1.1-1.04l3.64-4.06H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                </svg>
            </span>
        </div>
    </div>
</article>