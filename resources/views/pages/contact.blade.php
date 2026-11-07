@extends('layouts.app', [
'nav' => 'contact',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'Contact', 'url' => null],
],
'title' => 'Contact',
'description' => 'Call, WhatsApp or email '.config('agent.agency').', or send a message and get a reply within one business day.',
])

@php $agent = config('agent'); @endphp

@section('content')

{{-- Photography carries this page. The form sits in a frosted panel so the white
     fields stay the brightest thing on screen - that contrast is the whole point
     of the glass: it points at the form without hiding the photo behind it. The
     section is pulled up by the bar's height so the photo runs behind the bar
     rather than starting below it. --}}
<section class="relative isolate -mt-[88px] overflow-hidden bg-ink-950 pt-[88px]">
    {{-- This photo is the contact page's largest paint, so it stays eager and
         gets the same priority hint the home hero has. --}}
    <img src="{{ \App\Support\Media::agent('hero_image') }}" alt="" aria-hidden="true" decoding="async" fetchpriority="high"
        width="1600" height="900"
        class="absolute inset-0 -z-20 h-full w-full object-cover">

    {{-- A flat wash lifts the photo dark enough for white type, and the diagonal
         gradient stops the frosted panel dissolving into a bright patch of sky. --}}
    <div class="absolute inset-0 -z-10 bg-ink-950/70" aria-hidden="true"></div>
    <div class="absolute inset-0 -z-10 bg-gradient-to-tr from-ink-950/90 via-ink-950/60 to-ink-950/25" aria-hidden="true"></div>

    <div class="container-page grid gap-10 py-14 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1fr)] lg:items-start lg:gap-14 lg:py-20">
        <div>
            <p class="eyebrow text-brass-300">Contact</p>
            <h1 class="mt-4 text-4xl text-white sm:text-5xl">Let's talk.</h1>
            <p class="mt-5 max-w-md text-[17px] leading-relaxed text-sand-200">
                Call us directly, send a WhatsApp, or fill in the form and we'll reply within one business day.
                For anything urgent, the phone is always fastest.
            </p>

            <div class="mt-8 space-y-3">
                <a href="tel:{{ $agent['phone_dial'] }}" class="glass-row" data-analytics="click-to-call-contact">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white text-ink-900">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.2.4 2.4.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1l-2.3 2.2Z" />
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold tracking-[0.18em] text-sand-300 uppercase">Call or text</span>
                        <span class="block font-display text-xl text-white">{{ $agent['phone'] }}</span>
                    </span>
                </a>

                <a href="https://wa.me/{{ $agent['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="glass-row">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white">
                        <x-social-icon network="whatsapp" class="h-6 w-6" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold tracking-[0.18em] text-sand-300 uppercase">WhatsApp</span>
                        <span class="block font-sans text-lg font-semibold text-white">Start a chat</span>
                    </span>
                </a>

                <a href="mailto:{{ $agent['email'] }}" class="glass-row">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brass-500 text-white">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 13l9-5.5M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold tracking-[0.18em] text-sand-300 uppercase">Email</span>
                        <span class="block truncate font-sans text-lg font-semibold text-white">{{ $agent['email'] }}</span>
                    </span>
                </a>
            </div>

            {{-- Darker than the rows above: this block is reference material, not a
                 call to action, so it should sit back rather than compete. --}}
            <div class="mt-6 rounded-2xl border border-white/15 bg-ink-950/40 p-5 backdrop-blur-md">
                <h2 class="font-sans text-sm font-semibold text-white">Office</h2>
                <address class="mt-2 text-[15px] leading-relaxed text-sand-200 not-italic">
                    {{ $agent['office']['street'] }}<br>
                    {{ $agent['office']['suburb'] }} {{ $agent['office']['state'] }} {{ $agent['office']['postcode'] }}<br>
                    <a href="tel:{{ preg_replace('/\s+/', '', $agent['office_phone']) }}" class="text-brass-300 hover:underline">{{ $agent['office_phone'] }}</a>
                </address>

                <h3 class="mt-5 font-sans text-xs font-semibold tracking-[0.18em] text-sand-300 uppercase">Hours</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach ($agent['hours'] as $days => $hours)
                    <div class="flex justify-between gap-4">
                        <dt class="text-sand-300">{{ $days }}</dt>
                        <dd class="text-sand-100">{{ $hours }}</dd>
                    </div>
                    @endforeach
                </dl>

                <div class="mt-5 flex gap-2">
                    @foreach ($agent['social'] as $network => $url)
                    @continue(blank($url))
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-white/10 text-sand-100 transition hover:bg-white hover:text-ink-900"
                        aria-label="{{ ucfirst($network) }}">
                        <x-social-icon :network="$network" class="h-4 w-4" />
                    </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="glass-panel p-6 sm:p-8">
            @if (session('status'))
            <p class="mb-4 rounded-lg border border-emerald-300/40 bg-emerald-800/40 px-4 py-3 text-sm text-sand-100">{{ session('status') }}</p>
            @endif

            <x-enquiry-form
                type="contact"
                tone="glass"
                heading="Send a message"
                subheading="Tell us what you need and we will come back to you within one business day."
                submit-label="Send message" />
        </div>
    </div>
</section>

<section class="container-page py-16 pb-20">
    <x-service-map
        :areas="$agent['service_areas']"
        :embed-query="$agent['office']['street'].', '.$agent['office']['suburb'].' '.$agent['office']['state'].' '.$agent['office']['postcode']"
        :office-label="$agent['office']['street'].', '.$agent['office']['suburb']"
        :directions-url="'https://www.google.com/maps/dir/?api=1&destination='.urlencode($agent['office']['street'].', '.$agent['office']['suburb'].' '.$agent['office']['state'].' '.$agent['office']['postcode'])" />
</section>

@endsection