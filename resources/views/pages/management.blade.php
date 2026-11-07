@extends('layouts.app', [
'nav' => 'management',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'Property Management', 'url' => null],
],
'title' => 'Property Management',
'description' => 'Full property management in Dubai for owners and overseas investors - tenant placement, EJARI registration, rent collection, maintenance and a clear monthly statement. Your asset looked after, whether you live down the road or on the other side of the world.',
'schema' => [\App\Support\Seo::faq([
['What does property management cost?', 'A percentage of the collected rent, agreed in writing before we start. No management fee is charged in a month where there is no rent coming in.'],
['I live overseas - can you manage my property?', 'Yes. This is one of the most common jobs we do. We handle tenants, rent, EJARI and repairs locally, and send you a monthly statement and transfer the net rent out.'],
])],
])

@php $agent = config('agent'); @endphp

@section('content')

<x-page-hero eyebrow="Property Management" title="Own the property. We will handle everything else.">
    <p class="max-w-2xl text-[17px] leading-relaxed text-sand-200">
        A rented property is a job, not a passive line on a statement. Finding and screening the
        tenant, EJARI, collecting rent on time, answering the 9pm chiller call, keeping the service
        charges and the permits straight &mdash; we run all of it, and send you a clear monthly
        statement so you always know where things stand.
    </p>

    <ul class="mt-8 max-w-2xl space-y-3">
        @foreach ([
        'Marketing, tenant screening and a written tenancy contract',
        'EJARI registration, deposits and on-time transfers to your account',
        'Maintenance and repairs, with trusted trades and a call-first list',
        'Service charges, chiller and DEWA accounts kept current so nothing lapses',
        'A monthly statement - income, expenses and the balance, plainly set out',
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
        <a href="{{ route('selling') }}#appraisal" class="btn-accent">Get a rental appraisal</a>
        <a href="tel:{{ $agent['phone_dial'] }}" class="btn-ghost-light" data-analytics="click-to-call-management">Call {{ $agent['phone'] }}</a>
    </div>

    <x-slot:aside>
        <div id="enquire" class="glass-panel scroll-mt-28 p-6 sm:p-8">
            @if (session('status'))
            <p class="mb-4 rounded-lg border border-emerald-300/40 bg-emerald-800/40 px-4 py-3 text-sm text-sand-100">{{ session('status') }}</p>
            @endif

            <x-island
                name="EnquiryForm"
                :props="[
                    'type' => 'contact',
                    'endpoint' => route('enquiries.store'),
                    'tone' => 'glass',
                    'compact' => true,
                    'heading' => 'Ask about management',
                    'subheading' => 'Tell us where the property is and whether it is tenanted, and we will come back with what managing it would involve.',
                    'submitLabel' => 'Send request',
                ]">
                <h2 class="text-2xl font-semibold text-white sm:text-3xl">Ask about management</h2>
                <p class="mt-2 text-sand-200">Tell us where the property is and whether it is tenanted, and we will come back with what managing it would involve.</p>
                <x-form-skeleton />

                <noscript>
                    <style>
                        [data-form-skeleton] {
                            display: none
                        }
                    </style>
                    <form method="POST" action="{{ route('enquiries.store') }}" class="mt-6 space-y-4">
                        @csrf
                        <input type="hidden" name="type" value="contact">
                        <div>
                            <label class="label-on-dark" for="noscript-name">Your name</label>
                            <input id="noscript-name" name="name" type="text" class="input" required>
                        </div>
                        <div>
                            <label class="label-on-dark" for="noscript-email">Email</label>
                            <input id="noscript-email" name="email" type="email" class="input" required>
                        </div>
                        <div>
                            <label class="label-on-dark" for="noscript-message">Message</label>
                            <textarea id="noscript-message" name="message" rows="3" class="input"></textarea>
                        </div>
                        <button type="submit" class="btn-accent w-full">Send request</button>
                    </form>
                </noscript>
            </x-island>
        </div>
    </x-slot:aside>
</x-page-hero>

<section class="container-page py-20">
    <x-section-heading eyebrow="How it works" title="What you hand over, and what comes back to you" />

    <ol class="mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
        @foreach ([
        ['Take stock', 'We walk the property, note its condition, and agree a realistic rent from comparable lets in the same building and community - not a number to win the instruction.'],
        ['Find and screen the tenant', 'We market it, view interested parties, and run Emirates ID, income and reference checks before anyone signs.'],
        ['Sign a proper contract', 'Rent, term, notice, deposit and responsibilities for maintenance - all in writing and registered with EJARI, so there are no surprises six months in.'],
        ['Collect the rent', 'Rent is collected on the agreed cheque schedule and transferred to you, with any arrears chased as they arise rather than at the end of the quarter.'],
        ['Handle the day-to-day', 'Repairs, service charges, DEWA and the awkward calls are dealt with, using trades we trust and your call-first list for anything above a set amount.'],
        ['Report every month', 'A clear monthly statement: rent in, expenses out, the balance to you, and anything that needs a decision.'],
        ] as $i => [$heading, $body])
        @php
        $stepTones = ['bg-teal-500', 'bg-brass-500', 'bg-clay-500', 'bg-indigo-500', 'bg-teal-600', 'bg-brass-600'];
        @endphp
        <li class="card p-8">
            <span class="flex h-9 w-9 items-center justify-center rounded-full {{ $stepTones[$i % count($stepTones)] }} font-sans text-sm font-bold text-white">{{ $i + 1 }}</span>
            <h3 class="mt-4 font-sans text-lg font-semibold">{{ $heading }}</h3>
            <p class="mt-2 text-[15px] leading-relaxed text-ink-500">{{ $body }}</p>
        </li>
        @endforeach
    </ol>
</section>

<section class="border-y border-ink-100 bg-sand-100/60">
    <div class="container-page py-20">
        <div class="grid gap-10 lg:grid-cols-[1fr_1.1fr] lg:items-center">
            <div>
                <p class="eyebrow text-brass-600">For overseas owners</p>
                <h2 class="mt-3 text-3xl sm:text-4xl">Relocated or investing from afar? That is most of what we do.</h2>
                <p class="prose-page mt-4">
                    A lot of the properties we manage belong to people in London, Mumbai or Riyadh who
                    cannot drop by when a chiller fails or a tenant stops paying. You get a local team
                    that has actually walked the place, a monthly statement you can read in five
                    minutes, and the net rent transferred to your account - so distance stops being
                    the problem it feels like.
                </p>
            </div>

            <dl class="grid gap-8 sm:grid-cols-3">
                <x-stat :value="$agent['experience_years'].' yrs'" label="Managing property in Dubai" />
                <x-stat :value="count($agent['service_areas']).' areas'" label="Covered for tenants and trades" />
                <x-stat :value="'Monthly'" label="Statements and rent transfers" />
            </dl>
        </div>
    </div>
</section>

<section class="bg-ochre-50 border-y border-ochre-100">
    <div class="container-page py-20">
        <x-section-heading eyebrow="Management FAQs" title="Straight answers before you hand your property over" />

        <div class="mt-10 max-w-3xl divide-y divide-ochre-200">
            @foreach ([
            ['Do you decide on repairs without asking me?', 'Below an amount you set, we fix urgent problems straight away so they do not get worse, and bill them to the account. Anything above it, we call you first. Non-urgent work is always approved before it is ordered.'],
            ['What happens if a tenant stops paying?', 'We chase as soon as rent is late, not weeks later, and follow the notice process set out in the tenancy contract and RERA rules. You hear about it early, with the options, rather than at the end of the quarter.'],
            ['Can I get my property back when I want it?', 'Yes - subject to the notice period in the contract, which in Dubai is typically 90 days, and the reason for eviction. We serve it properly rather than by a phone call that does not hold up.'],
            ['Are the service charges and documents kept current?', 'That is part of the job. Service charge statements, DEWA and chiller accounts, title deed and insurance are tracked so nothing quietly lapses and turns into a problem when you finally need the file.'],
            ] as [$question, $answer])
            <details class="group py-5">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-sans text-lg font-semibold text-ink-900">
                    {{ $question }}
                    <svg class="h-5 w-5 shrink-0 text-ink-400 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                    </svg>
                </summary>
                <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-ink-600">{{ $answer }}</p>
            </details>
            @endforeach
        </div>
    </div>
</section>

<section class="container-page pb-20">
    <div class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-ink-900 via-ink-800 to-ink-700 p-8 text-center text-sand-100 sm:p-12">
        <div class="absolute -left-16 -bottom-16 -z-10 h-56 w-56 rounded-full bg-brass-500/25 blur-3xl" aria-hidden="true"></div>
        <h2 class="text-3xl text-white sm:text-4xl">Thinking of letting - or already have a tenant?</h2>
        <p class="mx-auto mt-4 max-w-xl text-ink-300">Tell us about the property and we will set out what managing it would involve, and what it costs.</p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="{{ route('contact') }}" class="btn-accent">Enquire about management</a>
            <a href="tel:{{ $agent['phone_dial'] }}" class="btn-ghost-light" data-analytics="click-to-call-management">Call {{ $agent['phone'] }}</a>
        </div>
    </div>
</section>

@endsection