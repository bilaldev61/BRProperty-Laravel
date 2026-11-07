@extends('layouts.app', [
'nav' => 'rentals',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'Renting Guide', 'url' => null],
],
'title' => 'Renting Guide',
'description' => 'Renting across Dubai - Palm Jumeirah, Dubai Marina, Business Bay, JVC and Dubai Hills. We help tenants find a fair home and landlords let it properly, with EJARI, DEWA and the paperwork handled.',
'schema' => [\App\Support\Seo::faq([
['Do you charge tenants a fee?', 'We only take a commission from the side that engaged us. If you approached us directly as a tenant, we will tell you the fee before you view anything.'],
['How do you screen a tenant?', 'Emirates ID or passport, proof of income, employment or rental history, a security deposit, and a reference from a previous landlord. We show the owner what we found before any contract is signed.'],
])],
])

@php $agent = config('agent'); @endphp

@section('content')

<x-page-hero eyebrow="Renting Guide" title="Renting, without the guesswork - for tenants and landlords both.">
    <p class="max-w-2xl text-[17px] leading-relaxed text-sand-200">
        A tenancy should be settled on clear terms, not a handshake and a hope. We match tenants to
        homes that actually suit them, and we help landlords let to the right people with the
        paperwork done properly &mdash; EJARI, DEWA, the deposit and a move-in condition report.
    </p>

    <ul class="mt-8 max-w-2xl space-y-3">
        @foreach ([
        'Apartments, villas and townhouses to rent across our communities',
        'A written tenancy contract registered with EJARI',
        'Tenant screening - Emirates ID, income and a reference before move-in',
        'A full inventory and condition report on the day you move in or out',
        ] as $item)
        <li class="flex gap-3 text-[15px] text-sand-200">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brass-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
            </svg>
            {{ $item }}
        </li>
        @endforeach
    </ul>

    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
        <a href="{{ route('for-rent') }}" class="btn-accent">Browse current rentals</a>
        <a href="{{ route('contact') }}" class="btn-ghost-light">Tell us what you want to rent</a>
    </div>

    <x-slot:aside>
        <div class="glass-panel p-6 sm:p-8">
            @if (session('status'))
            <p class="mb-4 rounded-lg border border-emerald-300/40 bg-emerald-800/40 px-4 py-3 text-sm text-sand-100">{{ session('status') }}</p>
            @endif

            <x-enquiry-form
                type="contact"
                tone="glass"
                :compact="true"
                heading="Request rental alerts"
                subheading="Tell us the area, type and budget, and we will send suitable rentals as they come up."
                submit-label="Send request" />
        </div>
    </x-slot:aside>
</x-page-hero>

<section class="bg-ink-900 text-sand-100">
    <div class="container-page py-20">
        <div class="max-w-2xl">
            <p class="eyebrow text-brass-300">Two sides of a tenancy</p>
            <h2 class="mt-3 text-3xl text-white sm:text-4xl">Whoever you are, we handle the awkward parts.</h2>
        </div>

        <div class="mt-12 grid gap-6 lg:grid-cols-2">
            <div class="rounded-3xl border border-indigo-400/25 bg-indigo-500/10 p-8">
                <span class="chip mb-4 bg-indigo-400/20 text-indigo-200">Tenants</span>
                <h3 class="font-sans text-xl font-semibold text-white">Finding a place to rent</h3>
                <p class="mt-3 text-ink-300">
                    Tell us the area, the type and your budget, and we shortlist honestly - including
                    what is wrong with each place. You view with someone who knows the building, and
                    the terms are clear before you pay anything.
                </p>
            </div>

            <div class="rounded-3xl border border-ochre-400/25 bg-ochre-500/10 p-8">
                <span class="chip mb-4 bg-ochre-400/20 text-ochre-200">Landlords</span>
                <h3 class="font-sans text-xl font-semibold text-white">Letting your property</h3>
                <p class="mt-3 text-ink-300">
                    We price the rent from comparable lets in your building, screen the tenant, draw up
                    and register the contract, and hand over with a documented condition report. If
                    you want us to run it month to month, our property management does exactly that.
                </p>
                <a href="{{ route('management') }}" class="btn-ghost-light mt-8">See property management</a>
            </div>
        </div>
    </div>
</section>

<section class="border-y border-ink-100 bg-sand-100/60">
    <div class="container-page py-20">
        <x-section-heading eyebrow="Rental FAQs" title="The questions worth asking before you sign" />

        <div class="mt-10 max-w-3xl divide-y divide-ink-200">
            @foreach ([
            ['What goes into a tenancy contract?', 'The rent and how it is paid, the term and the renewal and notice period, who covers maintenance and repairs, what happens to the security deposit and when it is returned, and a move-in inspection of the condition. If any of that is left verbal, it is not settled.'],
            ['How much is the deposit, and what about EJARI?', 'Usually 5% of the annual rent for an unfurnished property and 10% for a furnished one, held against the contract. The contract itself has to be registered with EJARI - that registration is what you will need for a residence visa, a school place or a DEWA connection.'],
            ['Who is responsible for repairs?', 'Structural and fixture problems are the landlord\'s; day-to-day wear and things the tenant breaks are the tenant\'s. Best agreed in writing up front, with a clear line for calling someone urgent.'],
            ['Can the rent go up at renewal?', 'Only in line with the RERA rental index for the property, and with the notice period set out in the contract - typically 90 days. A surprise rent hike is exactly the kind of thing we lock down before the contract is signed.'],
            ] as [$question, $answer])
            <details class="group py-5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-sans text-lg font-semibold text-ink-900">
                    {{ $question }}
                    <svg class="h-5 w-5 shrink-0 text-ink-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                    </svg>
                </summary>
                <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-ink-500">{{ $answer }}</p>
            </details>
            @endforeach
        </div>
    </div>
</section>

<section class="container-page pb-20">
    <div class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-ink-900 via-ink-800 to-ink-700 p-8 text-center text-sand-100 sm:p-12">
        <div class="absolute -right-16 -top-16 -z-10 h-56 w-56 rounded-full bg-brass-500/25 blur-3xl" aria-hidden="true"></div>
        <h2 class="text-3xl text-white sm:text-4xl">Looking to rent, or to let?</h2>
        <p class="mx-auto mt-4 max-w-xl text-ink-300">Tell us what you need and we will come back to you within one business day.</p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="{{ route('contact') }}" class="btn-accent">Get in touch</a>
            <a href="tel:{{ $agent['phone_dial'] }}" class="btn-ghost-light" data-analytics="click-to-call-rentals">Call {{ $agent['phone'] }}</a>
        </div>
    </div>
</section>

@endsection