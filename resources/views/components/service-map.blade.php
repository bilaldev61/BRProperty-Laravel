{{--
    Service-area map and office card.

    Server-rendered, no island: the frame, the office card and the area
    directory are all in the first paint. The iframe carries loading="lazy", so
    the browser itself defers the Google request until the section is near the
    viewport - no click gate, no JavaScript, and nothing that can change height
    after paint.

    Usage:
        <x-service-map :areas="$agent['service_areas']" :embed-query="..." :office-label="..." :directions-url="..." />
--}}
@props([
'areas' => [],
'embedQuery' => '',
'officeLabel' => '',
'directionsUrl' => '',
])

@php
$embed = $embedQuery !== ''
? 'https://maps.google.com/maps?q='.urlencode($embedQuery).'&output=embed&z=12'
: null;
@endphp

<div>
    <div class="flex flex-wrap items-end justify-between gap-6">
        <div class="max-w-2xl">
            <p class="eyebrow">Where we work</p>
            <h2 class="mt-3 text-3xl sm:text-4xl">Service areas</h2>
            <p class="mt-4 text-[17px] leading-relaxed text-ink-600">
                We work across Dubai and the surrounding communities, mostly these areas. Ask us about an address
                outside them - we will tell you honestly if it is not our patch.
            </p>
        </div>

        @if ($directionsUrl)
        <a href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer" class="btn-outline shrink-0">
            Get directions
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M9 7h8v8" />
            </svg>
        </a>
        @endif
    </div>

    <!-- The map is the section's illustration, so it gets the full width; the
         office card sits on it rather than in a column of its own. -->
    <div class="relative mt-10 overflow-hidden rounded-3xl border border-ink-100 bg-ink-100 shadow-sm">
        @if ($embed)
        <iframe
            src="{{ $embed }}"
            class="aspect-4/3 w-full sm:aspect-16/9"
            style="border: 0"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Map of {{ $officeLabel ?: $embedQuery }}"></iframe>
        @endif

        <div class="pointer-events-none absolute inset-x-4 bottom-4 sm:inset-x-6 sm:bottom-6 sm:max-w-xs">
            <div class="pointer-events-auto rounded-2xl border border-white/70 bg-white/95 p-5 shadow-xl shadow-ink-950/10 backdrop-blur">
                <p class="text-[11px] font-semibold tracking-[0.18em] text-brass-600 uppercase">Office</p>
                <p class="mt-2 text-sm leading-relaxed font-medium text-ink-900">{{ $officeLabel }}</p>
            </div>
        </div>
    </div>

    <p class="mt-3 text-xs text-ink-500">
        The map is drawn by Google Maps and loads separately from this page's own content.
    </p>

    <ul class="mt-8 grid grid-cols-2 gap-x-8 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        @foreach ($areas as $area)
        <li class="flex items-center gap-2.5 border-b border-ink-100 py-2.5 text-sm text-ink-600">
            <svg class="h-3.5 w-3.5 shrink-0 text-ink-300" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11Z" />
                <circle cx="12" cy="10" r="2.5" />
            </svg>
            <span class="truncate">{{ $area }}</span>
        </li>
        @endforeach
    </ul>
</div>