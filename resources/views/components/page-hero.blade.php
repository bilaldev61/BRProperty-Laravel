@props(['eyebrow' => null, 'title', 'lead' => null])

@php
// Anything put in the `aside` slot turns this into a two-column hero - which is
// how the pages with a form or a summary panel open.
$hasAside = isset($aside) && $aside->isNotEmpty();
@endphp

<section class="relative isolate overflow-hidden bg-ink-900 text-sand-100">
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="absolute -top-32 -right-20 h-72 w-72 rounded-full bg-brass-500/25 blur-3xl"></div>
        <div class="absolute -bottom-44 left-1/3 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl"></div>
    </div>

    <div @class(['container-page py-14 lg:py-20', 'grid gap-12 lg:grid-cols-[1.05fr_1fr] lg:items-center'=> $hasAside])>
        <div @class(['max-w-3xl'=> ! $hasAside])>
            @if ($eyebrow)
            <p class="eyebrow text-brass-300">{{ $eyebrow }}</p>
            @endif

            <h1 class="mt-4 text-4xl leading-[1.08] text-white sm:text-5xl lg:text-[3.25rem]">{{ $title }}</h1>

            @if ($lead)
            <p class="mt-5 max-w-2xl text-[17px] leading-relaxed text-sand-200">{{ $lead }}</p>
            @endif

            @if ($slot->isNotEmpty())
            <div class="mt-8">{{ $slot }}</div>
            @endif
        </div>

        @if ($hasAside)
        <div>{{ $aside }}</div>
        @endif
    </div>
</section>