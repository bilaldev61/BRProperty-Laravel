@extends('layouts.app', [
'nav' => 'testimonials',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'Reviews', 'url' => null],
],
'title' => 'Reviews',
'description' => 'Reviews from buyers, sellers and tenants who have completed a transaction with '.config('agent.agency').' across Dubai - Downtown, Business Bay, Dubai Marina, Arabian Ranches and Dubai Hills.',
])

@php
$agent = config('agent');
$count = $testimonials->count();
$average = $count ? round($testimonials->avg('rating'), 1) : 0;
$featured = $testimonials->where('is_featured', true)->take(3);
$rest = $testimonials->where('is_featured', false);

// Initials for the avatar disc: "Marcus & Elena Delgado" -> "MD".
$initials = fn (string $author) => \Illuminate\Support\Str::of($author)
->replaceMatches('/\b&\b/', '')
->explode(' ')
->filter()
->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
->take(2)
->implode('');
@endphp

@section('content')

{{-- Dark opening band: the number is the point of this page, so it gets the
     weight of the hero rather than sitting in a row of stars under the title. --}}
<section class="relative isolate overflow-hidden bg-ink-900 text-sand-100">
    <div class="absolute -top-24 right-0 -z-10 h-72 w-72 rounded-full bg-brass-500/20 blur-3xl" aria-hidden="true"></div>
    <div class="absolute -bottom-32 -left-16 -z-10 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl" aria-hidden="true"></div>

    <div class="container-page grid gap-10 py-16 lg:grid-cols-[1.35fr_1fr] lg:items-end lg:py-24">
        <div>
            <p class="eyebrow text-brass-300">Reviews</p>
            <h1 class="mt-4 text-4xl leading-[1.05] text-white sm:text-5xl lg:text-6xl">
                What it is actually like<br class="hidden sm:block"> to work with us.
            </h1>
            <p class="mt-6 max-w-xl text-[17px] leading-relaxed text-sand-200">
                Every review below is from a completed transaction. If you would like to speak to a past
                client directly before you list with us, just ask &mdash; we will put you in touch.
            </p>
        </div>

        @if ($count)
        <div class="rounded-3xl border border-white/15 bg-white/5 p-6 backdrop-blur-md sm:p-8">
            <div class="flex items-end gap-4">
                <p class="font-display text-6xl leading-none text-white">{{ number_format($average, 1) }}</p>
                <div class="pb-1">
                    <x-stars :rating="round($average)" size="h-4 w-4" tone="text-brass-400" />
                    <p class="mt-1.5 text-xs tracking-[0.14em] text-sand-300 uppercase">{{ $count }} reviews</p>
                </div>
            </div>
            <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-white/10 pt-5 text-sm">
                <div>
                    <dt class="text-sand-300">Sales completed</dt>
                    <dd class="mt-1 font-semibold text-white">{{ $agent['properties_sold'] }}+</dd>
                </div>
                <div>
                    <dt class="text-sand-300">Average on market</dt>
                    <dd class="mt-1 font-semibold text-white">{{ $agent['avg_days_on_market'] }} days</dd>
                </div>
            </dl>
        </div>
        @endif
    </div>
</section>

@if ($testimonials->isEmpty())
<section class="container-page py-20">
    <x-empty-state title="No reviews yet" message="Reviews will appear here as they come in." :action-url="route('contact')" action-label="Get in touch" />
</section>
@else

{{-- Featured three: one large quote carrying the page, two shorter ones
     stacked beside it. Big serif type is the whole design here. --}}
@if ($featured->isNotEmpty())
<section class="container-page -mt-10 lg:-mt-14">
    <div class="grid gap-5 lg:grid-cols-5">
        @foreach ($featured->take(2) as $testimonial)
        <figure @class([ 'relative flex flex-col rounded-3xl border p-7 sm:p-9' , 'bg-ink-900 text-sand-100 border-ink-900 lg:col-span-3'=> $loop->first,
            'bg-white border-ink-100 text-ink-800 lg:col-span-2' => ! $loop->first,
            ])>
            <svg @class(['h-8 w-8 shrink-0', 'text-brass-400'=> $loop->first, 'text-brass-500' => ! $loop->first])
                viewBox="0 0 32 32" fill="currentColor" aria-hidden="true">
                <path d="M13.4 6.6c-4.9 2.2-7.9 6-7.9 11.1 0 4.7 2.7 7.7 6.4 7.7 3.1 0 5.5-2.3 5.5-5.3 0-2.9-2.1-5.1-4.9-5.1-.6 0-1.2.1-1.5.2.5-2.6 2.7-5.2 5.4-6.6l-3-2Zm14.3 0c-4.9 2.2-7.9 6-7.9 11.1 0 4.7 2.7 7.7 6.4 7.7 3.1 0 5.5-2.3 5.5-5.3 0-2.9-2.1-5.1-4.9-5.1-.6 0-1.2.1-1.5.2.5-2.6 2.7-5.2 5.4-6.6l-3-2Z" />
            </svg>

            <blockquote @class(['mt-5 flex-1', 'font-display text-2xl leading-snug sm:text-[28px]'=> $loop->first, 'text-[17px] leading-relaxed' => ! $loop->first])>
                {{ $testimonial->body }}
            </blockquote>

            <figcaption class="mt-7 flex items-center gap-3 border-t pt-5 {{ $loop->first ? 'border-white/10' : 'border-ink-100' }}">
                <span @class([ 'flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold' , 'bg-brass-500 text-white'=> ! $loop->first,
                    'bg-white/10 text-sand-100' => $loop->first,
                    ])>{{ $initials($testimonial->author) }}</span>
                <span class="min-w-0">
                    <span @class(['block truncate font-semibold', 'text-white'=> $loop->first, 'text-ink-900' => ! $loop->first])>{{ $testimonial->author }}</span>
                    <span @class(['block truncate text-sm', 'text-sand-300'=> $loop->first, 'text-ink-500' => ! $loop->first])>
                        {{ collect([$testimonial->role, $testimonial->location])->filter()->implode(' · ') }}
                    </span>
                </span>
                <x-stars :rating="$testimonial->rating" size="h-3.5 w-3.5"
                    :tone="$loop->first ? 'text-brass-400' : 'text-brass-500'" class="ml-auto shrink-0" />
            </figcaption>
        </figure>
        @endforeach
    </div>
</section>
@endif

{{-- The rest: an even grid. Same anatomy as the featured cards so the two
     sections read as one system rather than two designs. --}}
@if ($rest->isNotEmpty())
<section class="container-page py-12 lg:py-16">
    <h2 class="text-2xl sm:text-3xl">More from the last few years.</h2>

    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($rest as $testimonial)
        <figure class="flex flex-col rounded-3xl border border-ink-100 bg-white p-6 transition duration-150 hover:-translate-y-0.5 hover:border-ink-300 hover:shadow-xl hover:shadow-ink-950/5">
            <div class="flex items-center justify-between gap-3">
                <x-stars :rating="$testimonial->rating" size="h-3.5 w-3.5" />
                @if ($testimonial->role)
                <span class="chip bg-sand-100 text-ink-500">{{ $testimonial->role }}</span>
                @endif
            </div>

            <blockquote class="mt-4 flex-1 text-[15px] leading-relaxed text-ink-600">{{ $testimonial->body }}</blockquote>

            <figcaption class="mt-6 flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink-900 text-xs font-semibold text-sand-50">{{ $initials($testimonial->author) }}</span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-ink-900">{{ $testimonial->author }}</span>
                    @if ($testimonial->location)
                    <span class="block truncate text-xs text-ink-500">{{ $testimonial->location }}</span>
                    @endif
                </span>
            </figcaption>
        </figure>
        @endforeach
    </div>
</section>
@endif

@endif

<section class="container-page pb-20">
    <div class="relative isolate overflow-hidden rounded-3xl bg-ink-900 px-6 py-12 text-center text-sand-100 sm:px-12 sm:py-16">
        <div class="absolute -right-16 -top-16 -z-10 h-56 w-56 rounded-full bg-brass-500/25 blur-3xl" aria-hidden="true"></div>
        <p class="eyebrow text-brass-300">Next step</p>
        <h2 class="mx-auto mt-4 max-w-2xl text-3xl text-white sm:text-4xl">Ready to talk about your place?</h2>
        <p class="mx-auto mt-4 max-w-xl text-sand-200">Start with a free valuation. It costs nothing and there is no obligation to list.</p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="{{ route('selling') }}#appraisal" class="btn-accent">Book a free valuation</a>
            <a href="tel:{{ $agent['phone_dial'] }}" class="btn-ghost-light" data-analytics="click-to-call-testimonials">Call {{ $agent['phone'] }}</a>
        </div>
    </div>
</section>

@endsection