@extends('layouts.app', [
'nav' => 'buying',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'Buying', 'url' => null],
],
'title' => 'Buying in Dubai',
'description' => 'What to expect when you buy a property in Dubai through '.config('agent.agency').' - viewings, title deed and NOC, Form F, the 4% Land Department fee, mortgage pre-approval and transfer day, explained step by step.',
])

@php $agent = config('agent'); @endphp

@section('content')

<x-page-hero eyebrow="Buying" title="Buy with someone who checks the title deed before you pay.">
    <p class="max-w-2xl text-[17px] leading-relaxed text-sand-200">
        Most agents work for the seller, and on our own listings so do we &mdash; but that is no
        reason to leave you guessing. You will get a straight answer about the title deed, the
        developer NOC, the service charges and whether the file is clean, before any money moves.
    </p>

    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
        <a href="{{ route('for-sale') }}" class="btn-accent">Browse current listings</a>
        <a href="tel:{{ $agent['phone_dial'] }}" class="btn-ghost-light" data-analytics="click-to-call-buying">Call {{ $agent['phone'] }}</a>
    </div>
</x-page-hero>

<section class="container-page py-20">
    <x-section-heading eyebrow="The process" title="From first viewing to keys in hand" />

    <ol class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach ([
        ['Get your mortgage pre-approval first', 'Cash or finance, know your ceiling before you start looking. UAE banks pre-approve non-residents as well as residents, and a buyer with a real approval letter negotiates better for it.'],
        ['View twice, at different times', 'Come once with us and once on your own. Traffic on the service road, the temperature of a west-facing living room in the afternoon, and how loud the chiller plant is all change through the day.'],
        ['Check the title deed and the NOC', 'Title deed in the seller\'s name, developer NOC, service charge statement, mortgage or levy against the unit, and for off-plan, the Oqood registration. We order all of it and read it.'],
        ['Form F and the deposit', 'The MOU - Form F - sets the price, the deposit (usually 10%), the handover date and who pays what. Deposit is held by the agency or the developer, never handed over on a handshake.'],
        ['Transfer at the Land Department', 'Both parties or their attorneys attend the DLD transfer, the 4% title fee and the agency commission are paid, and the deed is issued in your name the same day.'],
        ['Handover and snagging', 'Keys, DEWA connection, management or chiller accounts in your name, and any developer snagging logged formally rather than by a WhatsApp message that disappears.'],
        ] as $i => [$heading, $body])
        @php
        $stepTones = ['bg-teal-500', 'bg-brass-500', 'bg-clay-500', 'bg-indigo-500', 'bg-ochre-500', 'bg-plum-500'];
        @endphp
        <li class="card p-8">
            <span class="flex h-9 w-9 items-center justify-center rounded-full {{ $stepTones[$i % count($stepTones)] }} font-sans text-sm font-bold text-white">{{ $i + 1 }}</span>
            <h3 class="mt-4 font-sans text-lg font-semibold">{{ $heading }}</h3>
            <p class="mt-2 text-[15px] leading-relaxed text-ink-500">{{ $body }}</p>
        </li>
        @endforeach
    </ol>
</section>

<section class="border-y border-ink-100 bg-white">
    <div class="container-page py-20">
        <x-section-heading eyebrow="Questions we get asked" title="Buyer FAQs" />

        <div class="mt-10 max-w-3xl divide-y divide-ink-200">
            @foreach ([
            ['How do I know the title is clean?', 'A title deed in the seller\'s name, a current developer NOC with no service charge arrears, no mortgage or levy registered against the unit, and for off-plan, a confirmed Oqood registration. We order these and read them, not a photo on a text message.'],
            ['What does buying actually cost on top of the price?', 'Budget roughly 4% Land Department transfer fee, about 2% agency commission, a few thousand dirhams in DLD administration charges, and the developer NOC fee. If you are financing, add the bank arrangement fee and valuation. We give you the full number in writing before you offer.'],
            ['Can a non-resident buy in Dubai?', 'Yes. Freehold areas are open to foreign buyers on the same terms as residents, and title is held in your own name. Talk to us about which communities are freehold before you fall for a view.'],
            ['What is the deposit and can it be refunded?', 'Usually 10%, held against the Form F. Whether it comes back depends entirely on the terms you agree - which is why we write them down. A verbal understanding is worth nothing in a dispute.'],
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

@if ($listings->isNotEmpty())
<section class="container-page py-20">
    <div class="flex flex-wrap items-end justify-between gap-6">
        <x-section-heading eyebrow="Available now" title="Currently for sale" />
        <a href="{{ route('for-sale') }}" class="btn-outline shrink-0">View all</a>
    </div>
    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($listings as $property)
        <x-property-card :property="$property" />
        @endforeach
    </div>
</section>
@endif

@endsection