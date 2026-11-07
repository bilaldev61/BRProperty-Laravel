@extends('layouts.app', ['nav' => 'home'])

@php
$agent = config('agent');
// The wordmark is set in two weights, so the agency name is split at its last
// capital - "BRProperty" becomes "BR" + "Property" - without hard-coding either half.
$brand = $agent['agency'];
$brandParts = preg_match('/^(.+?)([A-Z][^A-Z]*)$/u', $brand, $m) ? [$m[1], $m[2]] : [$brand, ''];
@endphp

@section('content')

{{--
    Hero. The photograph runs full-bleed behind the floating bar, so the section is
    pulled up by exactly the bar's height (88px) and pads the same amount back in -
    which is what lets the photo show behind the glass pill instead of starting
    underneath it.
--}}
<section class="relative isolate -mt-[88px] flex min-h-[92svh] flex-col overflow-hidden pt-[88px]">
    <img
        src="{{ \App\Support\Media::agent('hero_image') }}"
        alt=""
        class="absolute inset-0 -z-10 h-full w-full object-cover"
        width="1600" height="900"
        fetchpriority="high" decoding="async">
    {{-- Two scrims: a flat wash lifts the whole frame, and a vertical gradient keeps
         the centred type readable top and bottom while the middle stays photographic. --}}
    <div class="absolute inset-0 -z-10 bg-ink-950/45" aria-hidden="true"></div>
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-ink-950/80 via-ink-950/20 to-ink-950/85" aria-hidden="true"></div>

    <div class="container-page relative flex flex-1 flex-col items-center justify-center py-20 text-center sm:py-24">
        <p class="eyebrow text-brass-200">{{ $agent['descriptor'] }} &middot; {{ $agent['office']['state'] }}</p>

        <h1 class="mt-6 font-sans text-5xl leading-none tracking-[-0.03em] text-white sm:text-7xl lg:text-8xl">
            <span class="sr-only">{{ $agent['agency'] }} - {{ $agent['office']['state'] }} real estate</span>
            <span aria-hidden="true"><span class="font-bold">{{ $brandParts[0] }}</span><span class="font-light text-sand-100">{{ $brandParts[1] }}</span></span>
        </h1>

        <p class="mt-6 font-display text-xl text-sand-100 italic sm:text-2xl">
            Where {{ $agent['office']['state'] }} finds its address.
        </p>

        <p class="mt-6 max-w-xl text-sm leading-relaxed text-sand-100/80 sm:text-base">
            {{ $agent['properties_sold'] }}+ properties sold across Dubai and the western crescent -
            every one of them with the title deed checked before you commit.
        </p>

        <div class="mt-9 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('for-sale') }}" class="btn-accent">Browse properties for sale</a>
            <a href="{{ route('selling') }}#appraisal" class="btn-ghost-light">Get a free valuation</a>
        </div>
    </div>
</section>

{{-- Credentials strip: the numbers that matter, out of the hero so the photo can breathe --}}
<section class="border-b border-ink-100 bg-sand-100/70">
    <dl class="container-page grid grid-cols-3 gap-4 py-8 text-center sm:gap-8">
        <div>
            <dd class="font-display text-3xl text-ink-900 sm:text-4xl">{{ $agent['properties_sold'] }}+</dd>
            <dt class="mt-1 text-[11px] tracking-wide text-ink-500 uppercase sm:text-xs">Properties sold</dt>
        </div>
        <div class="border-x border-ink-200/70">
            <dd class="font-display text-3xl text-ink-900 sm:text-4xl">{{ $agent['avg_days_on_market'] }}</dd>
            <dt class="mt-1 text-[11px] tracking-wide text-ink-500 uppercase sm:text-xs">Avg days on market</dt>
        </div>
        <div>
            <dd class="font-display text-3xl text-ink-900 sm:text-4xl">{{ $agent['experience_years'] }}</dd>
            <dt class="mt-1 text-[11px] tracking-wide text-ink-500 uppercase sm:text-xs">Years in Dubai</dt>
        </div>
    </dl>
</section>

{{-- Featured listings --}}
<section class="container-page py-20">
    <div class="flex flex-wrap items-end justify-between gap-6">
        <x-section-heading
            eyebrow="On the market"
            title="Properties for sale"
            lead="Every listing here is one we have walked ourselves, priced from recent transfers in the same block - not from a number designed to win the listing." />
        <a href="{{ route('for-sale') }}" class="btn-outline shrink-0">View all listings</a>
    </div>

    @if ($featured->isEmpty())
    <div class="mt-10">
        <x-empty-state
            title="No listings right now"
            message="New campaigns launch most weeks. Register your interest and we'll send them before they hit the portals."
            :action-url="route('contact')"
            action-label="Register your interest" />
    </div>
    @else
    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($featured as $property)
        <x-property-card :property="$property" :eager="$loop->first" />
        @endforeach
    </div>
    @endif
</section>

{{-- Services split --}}
<section class="bg-ink-900 text-sand-100">
    <div class="container-page py-20">
        <div class="max-w-2xl">
            <p class="eyebrow text-brass-300">How we can help</p>
            <h2 class="mt-3 text-3xl text-white sm:text-4xl">Every side of the market, handled properly.</h2>
        </div>

        <div class="mt-12 grid gap-6 lg:grid-cols-2">
            <div class="rounded-card border border-brass-400/25 bg-brass-500/10 p-8">
                <span class="chip mb-4 bg-brass-400/20 text-brass-200">Sellers</span>
                <h3 class="font-sans text-xl font-semibold text-white">Selling</h3>
                <p class="mt-3 text-ink-300">
                    A price set from recent transfers in your own block, listings placed where your buyer
                    actually looks, and a weekly update so you always know where things stand.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-ink-200">
                    @foreach (['Free, no-obligation market valuation', 'Professional photography included', 'Weekly written updates', 'Negotiation and Land Department transfer handled by us'] as $item)
                    <li class="flex gap-3">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-brass-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                        </svg>
                        {{ $item }}
                    </li>
                    @endforeach
                </ul>
                <a href="{{ route('selling') }}" class="btn-ghost-light mt-8">Selling &amp; free valuation</a>
            </div>

            <div class="rounded-card border border-clay-400/25 bg-clay-500/10 p-8">
                <span class="chip mb-4 bg-clay-400/20 text-clay-200">Buyers</span>
                <h3 class="font-sans text-xl font-semibold text-white">Buying</h3>
                <p class="mt-3 text-ink-300">
                    Early word on what is coming up, honest answers about what is wrong with a property as
                    well as what is right, and the title deed checked before you pay a deposit.
                </p>
                <ul class="mt-6 space-y-3 text-sm text-ink-200">
                    @foreach (['Off-market and coming-soon alerts', 'Title deed, NOC and service charges checked before you pay', 'Viewings arranged around your schedule', 'Introductions to trusted mortgage brokers and conveyancers'] as $item)
                    <li class="flex gap-3">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-clay-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                        </svg>
                        {{ $item }}
                    </li>
                    @endforeach
                </ul>
                <a href="{{ route('buying') }}" class="btn-ghost-light mt-8">Buying with us</a>
            </div>

            <div class="rounded-card border border-indigo-400/25 bg-indigo-500/10 p-8">
                <span class="chip mb-4 bg-indigo-400/20 text-indigo-200">Rentals</span>
                <h3 class="font-sans text-xl font-semibold text-white">Renting</h3>
                <p class="mt-3 text-ink-300">
                    Homes to let across our areas, with the tenant screening, written agreement and handover
                    inventory done properly - for tenants and landlords both.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('for-rent') }}" class="btn-ghost-light">See rentals</a>
                    <a href="{{ route('rentals') }}" class="text-sm font-semibold text-ink-300 underline decoration-indigo-400/50 underline-offset-4 hover:text-white">How renting works</a>
                </div>
            </div>

            <div class="rounded-card border border-ochre-400/25 bg-ochre-500/10 p-8">
                <span class="chip mb-4 bg-ochre-400/20 text-ochre-200">Owners</span>
                <h3 class="font-sans text-xl font-semibold text-white">Property Management</h3>
                <p class="mt-3 text-ink-300">
                    We run your rented property end to end - rent collection, maintenance, dues and a monthly
                    statement - whether you live down the road or overseas.
                </p>
                <a href="{{ route('management') }}" class="btn-ghost-light mt-8">Manage your property</a>
            </div>
        </div>
    </div>
</section>

{{-- Recently sold --}}
@if ($recentlySold->isNotEmpty())
<section class="border-y border-ink-100 bg-sand-100/60">
    <div class="container-page py-20">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <x-section-heading
                eyebrow="Results"
                title="Recently sold"
                :lead="'Sales we have completed across Dubai in the last few months.'" />
            <a href="{{ route('sold') }}" class="btn-outline shrink-0">See all sales</a>
        </div>

        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($recentlySold as $property)
            <x-property-card :property="$property" />
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Testimonials. Split rather than centred: the score and the count answer "is
     this place any good?" on the left, and one review at a time carries the proof
     on the right. --}}
@if ($testimonials->isNotEmpty())
@php
$featured = $testimonials->first();
$reviewAverage = round((float) $testimonials->avg('rating'), 1);
$reviewCount = $testimonials->count();
@endphp
<section class="border-b border-ink-100 bg-white">
    <div class="container-page py-20 lg:py-24">
        <div class="grid gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:items-center lg:gap-16">
            <div>
                <p class="eyebrow">Reviews</p>
                <h2 class="mt-3 text-3xl sm:text-4xl">In their words, not ours.</h2>
                <p class="mt-5 max-w-md text-[17px] leading-relaxed text-ink-600">
                    Nothing cherry-picked. Every quote below is from a client we completed a sale or a let with.
                </p>

                <div class="mt-8 flex items-center gap-5 border-t border-ink-100 pt-7">
                    <p class="font-display text-5xl leading-none text-ink-900">{{ number_format($reviewAverage, 1) }}</p>
                    <div>
                        <x-stars :rating="(int) round($reviewAverage)" />
                        <p class="mt-2 text-sm text-ink-500">
                            Across {{ $reviewCount }} {{ $reviewCount === 1 ? 'review' : 'reviews' }}
                        </p>
                    </div>
                </div>

                <a href="{{ route('testimonials') }}" class="btn-outline mt-8">
                    Read all {{ $reviewCount === 1 ? 'review' : 'reviews' }}
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </a>
            </div>

            <x-island
                name="TestimonialCarousel"
                :props="['items' => $testimonials->map(fn ($t) => [
                    'body' => $t->body, 'author' => $t->author, 'location' => $t->location,
                    'role' => $t->role, 'rating' => $t->rating,
                ])]">
                {{-- Server-rendered fallback, replaced by the carousel on hydrate --}}
                <blockquote class="rounded-3xl border border-ink-100 bg-sand-50 p-8 sm:p-10">
                    <x-stars :rating="$featured->rating" />
                    <p class="mt-6 font-display text-xl leading-relaxed text-ink-900 sm:text-2xl">&ldquo;{{ $featured->body }}&rdquo;</p>
                    <footer class="mt-8 text-sm">
                        <span class="block font-semibold text-ink-900">{{ $featured->author }}</span>
                        @if ($featured->location)
                        <span class="block text-ink-500">{{ $featured->location }}</span>
                        @endif
                    </footer>
                </blockquote>
            </x-island>
        </div>
    </div>
</section>
@endif

{{-- Service area map --}}
<section class="container-page py-20">
    <x-service-map
        :areas="$agent['service_areas']"
        :embed-query="$agent['office']['street'].', '.$agent['office']['suburb'].', '.$agent['office']['state'].' '.$agent['office']['postcode']"
        :office-label="$agent['office']['street'].', '.$agent['office']['suburb']"
        :directions-url="'https://www.google.com/maps/dir/?api=1&destination='.urlencode($agent['office']['street'].', '.$agent['office']['suburb'].' '.$agent['office']['state'].' '.$agent['office']['postcode'])" />
</section>

{{-- Closing CTA. Same treatment as the contact page - a photograph behind a
     frosted panel - so the valuation form reads as the same form rather than a
     second design, and its white fields stay the brightest thing on screen. --}}
<section class="container-page pb-20">
    <div class="relative isolate grid gap-10 overflow-hidden rounded-3xl bg-ink-950 p-8 text-sand-100 sm:p-12 lg:grid-cols-2 lg:items-center">
        <img src="{{ \App\Support\Media::agent('hero_image') }}" alt="" aria-hidden="true" decoding="async" loading="lazy"
            class="absolute inset-0 -z-20 h-full w-full object-cover">
        <div class="absolute inset-0 -z-10 bg-ink-950/80" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-tr from-ink-950/90 via-ink-950/60 to-ink-950/20" aria-hidden="true"></div>
        <div>
            <p class="eyebrow text-brass-300">No obligation</p>
            <h2 class="mt-3 text-3xl text-white sm:text-4xl">What is your property actually worth?</h2>
            <p class="mt-4 max-w-md text-[17px] leading-relaxed text-sand-200">
                We'll visit the property, pull the transfer evidence from your own building or community, and
                give you a written range with the reasoning attached. If the answer is "wait six months", we'll say so.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="tel:{{ $agent['phone_dial'] }}" class="btn-accent" data-analytics="click-to-call-cta">Call {{ $agent['phone'] }}</a>
                <a href="https://wa.me/{{ $agent['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="btn-ghost-light">WhatsApp instead</a>
            </div>
        </div>

        <div class="glass-panel p-6 sm:p-8">
            @if (session('status'))
            <p class="mb-4 rounded-lg border border-emerald-300/40 bg-emerald-800/40 px-4 py-3 text-sm text-sand-100">{{ session('status') }}</p>
            @endif

            <x-island
                name="EnquiryForm"
                :props="[
                    'type' => 'appraisal',
                    'endpoint' => route('enquiries.store'),
                    'types' => config('agent.property_types'),
                    'tone' => 'glass',
                    'compact' => true,
                    'heading' => 'Book your free valuation',
                    'submitLabel' => 'Request my valuation',
                ]">
                <h2 class="text-2xl font-semibold text-white sm:text-3xl">Book your free valuation</h2>
                <x-form-skeleton :fields="5" />

                <noscript>
                    <style>
                        [data-form-skeleton] {
                            display: none
                        }
                    </style>
                    <form method="POST" action="{{ route('enquiries.store') }}" class="mt-6 space-y-4">
                        @csrf
                        <input type="hidden" name="type" value="appraisal">
                        <div>
                            <label class="label-on-dark" for="noscript-name">Your name</label>
                            <input id="noscript-name" name="name" type="text" class="input" required>
                        </div>
                        <div>
                            <label class="label-on-dark" for="noscript-email">Email</label>
                            <input id="noscript-email" name="email" type="email" class="input" required>
                        </div>
                        <div>
                            <label class="label-on-dark" for="noscript-address">Property address</label>
                            <input id="noscript-address" name="address" type="text" class="input">
                        </div>
                        <button type="submit" class="btn-accent w-full">Request my valuation</button>
                    </form>
                </noscript>
            </x-island>
        </div>
    </div>
</section>

@endsection