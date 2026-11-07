@extends('layouts.app', [
'nav' => 'about',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'About', 'url' => null],
],
'title' => 'About '.config('agent.agency'),
'description' => 'Meet '.config('agent.name').', founder of '.config('agent.agency').', a Dubai real estate agency working across Palm Jumeirah, Downtown, Dubai Marina, Business Bay, Dubai Hills and the wider emirate.',
])

@php $agent = config('agent'); @endphp

@section('content')

<x-page-hero eyebrow="Meet the founder" :title="$agent['name']">
    <p class="mt-2 text-lg text-sand-300">Founder of {{ $agent['agency'] }} &middot; {{ $agent['license'] }}</p>

    <div class="prose-page-dark mt-6">
        @foreach ($agent['bio'] as $paragraph)
        <p>{{ $paragraph }}</p>
        @endforeach
    </div>

    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
        <a href="tel:{{ $agent['phone_dial'] }}" class="btn-accent" data-analytics="click-to-call-about">Call {{ $agent['phone'] }}</a>
        <a href="{{ route('contact') }}" class="btn-ghost-light">Send a message</a>
    </div>

    <x-slot:aside>
        <div class="relative mx-auto max-w-md">
            <div class="absolute -inset-3 -z-10 rounded-[2rem] border border-brass-400/30" aria-hidden="true"></div>
            <img
                src="{{ \App\Support\Media::agent('photo') }}"
                alt="Portrait of {{ $agent['name'] }}"
                class="w-full rounded-[1.75rem] object-cover shadow-2xl shadow-ink-950/60"
                width="384" height="384" decoding="async" fetchpriority="high">

            <div class="absolute -bottom-5 left-5 right-5 rounded-2xl border border-white/15 bg-ink-950/70 px-5 py-4 backdrop-blur-md sm:left-8 sm:right-8">
                <p class="text-sm font-semibold text-white">{{ $agent['office']['suburb'] }}, {{ $agent['office']['state'] }}</p>
                <p class="mt-0.5 text-xs text-sand-300">{{ $agent['office']['street'] }}</p>
            </div>
        </div>
    </x-slot:aside>
</x-page-hero>

<section class="border-b border-ink-100 bg-white">
    <div class="container-page grid gap-8 py-12 sm:grid-cols-3">
        <x-stat :value="$agent['properties_sold'].'+'" label="Properties sold across Dubai" />
        <x-stat :value="$agent['avg_days_on_market'].' days'" label="Average time on market" />
        <x-stat :value="count($agent['service_areas']).' areas'" label="Covered in detail, week in week out" />
    </div>
</section>

<section class="container-page py-20">
    <x-section-heading
        eyebrow="How we work"
        title="Three commitments we make to every client"
        align="center" />

    <div class="mt-12 grid gap-6 md:grid-cols-3">
        @foreach ([
        ['We price on evidence, not optimism', 'A valuation that flatters you costs you money later. We show you the Land Department transfers the number came from, and we explain the ones that do not support it.'],
        ['We check the title before you pay', 'Title deed, developer NOC, service charge statement, any mortgage or levy registered against the unit, and the Oqood registration on off-plan. We verify all of it ourselves before any money changes hands.'],
        ['We tell you the awkward part', 'If there are arrears on the service charges, if the price expectation is high, if the developer has form for late handover - you hear it from us first, not from the trustee office.'],
        ] as $i => [$heading, $body])
        @php $tone = [['bg-teal-50 border-teal-200', 'text-teal-500'],
        ['bg-brass-50 border-brass-200', 'text-brass-500'],
        ['bg-clay-50 border-clay-200', 'text-clay-500']][$i]; @endphp
        <div class="rounded-3xl border p-8 {{ $tone[0] }}">
            <span class="font-display text-4xl {{ $tone[1] }}">0{{ $i + 1 }}</span>
            <h3 class="mt-4 font-sans text-lg font-semibold">{{ $heading }}</h3>
            <p class="mt-3 text-[15px] leading-relaxed text-ink-500">{{ $body }}</p>
        </div>
        @endforeach
    </div>
</section>

@if ($testimonials->isNotEmpty())
<section class="border-y border-ink-100 bg-sand-100/60">
    <div class="container-page py-20">
        <x-section-heading eyebrow="In their words" title="What clients say" align="center" />

        <div class="mx-auto mt-12 grid max-w-5xl gap-6 md:grid-cols-2">
            @foreach ($testimonials as $testimonial)
            <blockquote class="flex flex-col rounded-3xl border border-ink-100 bg-white p-8 shadow-sm shadow-ink-950/5">
                <x-stars :rating="$testimonial->rating" />
                <p class="mt-4 flex-1 text-[15px] leading-relaxed text-ink-600">&ldquo;{{ $testimonial->body }}&rdquo;</p>
                <footer class="mt-6 flex items-center gap-3 border-t border-ink-100 pt-5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-ink-900 font-sans text-xs font-bold text-sand-50">
                        {{ \Illuminate\Support\Str::of($testimonial->author)->explode(' ')->filter()->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->take(2)->implode('') }}
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-ink-900">{{ $testimonial->author }}</p>
                        <p class="text-xs text-ink-400">{{ $testimonial->role }} &middot; {{ $testimonial->location }}</p>
                    </div>
                </footer>
            </blockquote>
            @endforeach
        </div>

        <p class="mt-10 text-center">
            <a href="{{ route('testimonials') }}" class="btn-outline">Read all reviews</a>
        </p>
    </div>
</section>
@endif

<section class="container-page pb-20">
    <div class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-ink-900 via-ink-800 to-ink-700 p-8 text-center text-sand-100 sm:p-12">
        <div class="absolute -right-16 -top-16 -z-10 h-56 w-56 rounded-full bg-brass-500/25 blur-3xl" aria-hidden="true"></div>
        <h2 class="text-3xl text-white sm:text-4xl">Thinking about a move?</h2>
        <p class="mx-auto mt-4 max-w-xl text-ink-300">Tell us what you are weighing up and we will tell you honestly whether now is the moment.</p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="{{ route('contact') }}" class="btn-accent">Get in touch</a>
            <a href="{{ route('selling') }}#appraisal" class="btn-ghost-light">Book a free valuation</a>
        </div>
    </div>
</section>

@endsection