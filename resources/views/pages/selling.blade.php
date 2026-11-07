@extends('layouts.app', [
'nav' => 'selling',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'Selling & Free Appraisal', 'url' => null],
],
'schema' => [\App\Support\Seo::faq([
['Is the appraisal really free?', 'Yes. You get a written appraisal whether or not you list with '.config('agent.agency').'.'],
['What is the commission?', 'In Dubai the agency side commonly agrees around 2% of the sale price plus VAT, set out in writing before you commit. We are upfront about the full number.'],
['Do I need a Trakheesi permit to advertise?', 'Yes. Every listing advertised in Dubai needs a permit number from the Real Estate Regulatory Agency. We apply for it, and it costs you nothing extra.'],
['How long will it take?', 'The average across recent sales is '.config('agent.avg_days_on_market').' days from listing to an accepted offer.'],
])],
'title' => 'Selling & Free Appraisal',
'description' => 'Book a free, no-obligation property valuation with '.config('agent.agency').' in Dubai. Pricing from Land Department transfers, professional photography, a Trakheesi permit and weekly updates.',
])

@php $agent = config('agent'); @endphp

@section('content')

<x-page-hero eyebrow="Selling" title="Find out what your property is worth. Free, and genuinely no obligation.">
    <p class="max-w-2xl text-[17px] leading-relaxed text-sand-200">
        An appraisal should be research, not a sales pitch. We visit the property, pull every
        recent Land Department transfer in your building and the ones around it, and give you a
        written range with the reasoning attached &mdash; including the sales that argue against the top of it.
    </p>

    <ul class="mt-8 max-w-2xl space-y-3">
        @foreach ([
        'A written price range, backed by recent transfers in your building and community',
        'An honest list of what to fix and what to leave alone',
        'A full cost breakdown - agency commission, DLD fee, NOC and trustee charges - before you commit',
        'No lock-in beyond the term we agree, no pressure, no follow-up you did not ask for',
        ] as $item)
        <li class="flex gap-3 text-[15px] text-sand-200">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-brass-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
            </svg>
            {{ $item }}
        </li>
        @endforeach
    </ul>

    <x-slot:aside>
        <div id="appraisal" class="glass-panel scroll-mt-28 p-6 sm:p-8">
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
                    'heading' => 'Request your free appraisal',
                    'subheading' => 'We reply to every request within one business day.',
                    'submitLabel' => 'Request my free appraisal',
                ]">
                <h2 class="text-2xl font-semibold text-white sm:text-3xl">Request your free appraisal</h2>
                <p class="mt-2 text-sand-200">We reply to every request within one business day.</p>
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
                        <button type="submit" class="btn-accent w-full">Request my free appraisal</button>
                    </form>
                </noscript>
            </x-island>
        </div>
    </x-slot:aside>
</x-page-hero>

<section class="container-page py-20">
    <x-section-heading eyebrow="The campaign" title="What the six weeks actually look like" />

    <ol class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
        ['Week 0', 'Appraisal and paperwork', 'We agree a price range, and we pull your own file first - title deed, service charges, developer NOC - so nothing surprises us at the end.'],
        ['Week 1', 'Prepare and photograph', 'A clean-up and minor fixes if they are worth it, then professional photographs, floor plan and a video walkthrough.'],
        ['Weeks 2-4', 'List and show', 'With a Trakheesi permit in place: Bayut, Property Finder, Dubizzle, our own buyer list and the broker network. Written feedback after every viewing.'],
        ['Weeks 4-6', 'Negotiate and transfer', 'Offers brought to you in full, Form F and the 10% deposit, then the DLD transfer, NOC and final meter readings.'],
        ] as [$phase, $heading, $body])
        @php $phaseTone = ['teal', 'brass', 'clay', 'indigo'][$loop->index]; @endphp
        <li class="card border-t-4 p-6 border-t-{{ $phaseTone }}-500">
            <p class="eyebrow text-{{ $phaseTone }}-600">{{ $phase }}</p>
            <h3 class="mt-3 font-sans text-lg font-semibold">{{ $heading }}</h3>
            <p class="mt-2 text-[15px] leading-relaxed text-ink-500">{{ $body }}</p>
        </li>
        @endforeach
    </ol>
</section>

<section class="border-y border-ink-100 bg-sand-100/60">
    <div class="container-page py-20">
        <x-section-heading eyebrow="Seller FAQs" title="The questions worth asking any agent" />

        <div class="mt-10 max-w-3xl divide-y divide-ink-200">
            @foreach ([
            ['Is the appraisal really free?', 'Yes. You get a written appraisal whether or not you list with us, and whether or not you sell this year. It costs us an hour and a bit of research.'],
            ['What does selling actually cost me?', 'Agency commission, usually agreed around 2% of the price plus VAT. The 4% Land Department transfer fee is customarily the buyer\'s. Add the developer NOC fee and the trustee office charge on transfer day. You get the full number in writing before you commit to anything.'],
            ['Should I renovate before selling?', 'Usually not much. Paint, a deep clean and clearing clutter almost always return more than they cost. A new kitchen almost never does. We will give you a short list, not a wish list.'],
            ['Do I need a Trakheesi permit to advertise?', 'Yes. Every property advertised in Dubai needs a permit number from the Real Estate Regulatory Agency, and advertising without one is a fine waiting to happen. We apply for it on your behalf and it costs you nothing extra.'],
            ['How long will it take?', 'Our average across recent sales is '.config('agent.avg_days_on_market').' days from listing to an accepted offer. Pricing moves that number more than anything else does.'],
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

@if ($recentlySold->isNotEmpty())
<section class="container-page py-20">
    <div class="flex flex-wrap items-end justify-between gap-6">
        <x-section-heading eyebrow="Proof" title="Recent results" />
        <a href="{{ route('sold') }}" class="btn-outline shrink-0">All recent sales</a>
    </div>
    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($recentlySold as $property)
        <x-property-card :property="$property" />
        @endforeach
    </div>
</section>
@endif

@endsection