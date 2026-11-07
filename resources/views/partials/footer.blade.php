@php
$agent = config('agent');
$directions = 'https://www.google.com/maps/dir/?api=1&destination='
.urlencode($agent['office']['street'].', '.$agent['office']['suburb'].' '.$agent['office']['state'].' '.$agent['office']['postcode']);
@endphp

{{--
    Footer. Two jobs, in this order: say who we are, then make it obvious how to
    reach a person. The navigation, the office and the areas sit underneath as
    reference rather than competing with the phone number.
--}}
<footer class="relative isolate mt-24 overflow-hidden bg-ink-950 text-sand-300">
    <div class="pointer-events-none absolute -top-32 right-0 -z-10 h-72 w-72 rounded-full bg-brass-500/10 blur-3xl" aria-hidden="true"></div>
    <div class="h-px w-full bg-gradient-to-r from-transparent via-brass-500/60 to-transparent" aria-hidden="true"></div>

    <div class="container-page">

        {{-- Brand and direct contact --}}
        <div class="grid gap-10 py-14 lg:grid-cols-[1.15fr_1fr] lg:items-end">
            <div>
                <img src="{{ \App\Support\Media::agent('logo_light') }}" alt="{{ $agent['agency'] }}" width="230" height="55" class="h-12 w-auto">
                <p class="mt-5 max-w-lg text-[15px] leading-relaxed">{{ $agent['tagline'] }}</p>
                <p class="mt-4 text-[11px] font-semibold tracking-[0.18em] text-brass-300 uppercase">
                    {{ $agent['name'] }} &middot; {{ $agent['title'] }}
                </p>

                <div class="mt-5 flex gap-2">
                    @foreach ($agent['social'] as $network => $url)
                    @continue(blank($url))
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                        class="flex h-9 w-9 items-center justify-center rounded-full border border-white/15 text-sand-200 transition hover:border-white/40 hover:bg-white/10 hover:text-white"
                        aria-label="{{ ucfirst($network) }}">
                        <x-social-icon :network="$network" class="h-4 w-4" />
                    </a>
                    @endforeach
                </div>
            </div>

            <div class="lg:justify-self-end lg:text-right">
                <p class="text-[11px] font-semibold tracking-[0.18em] text-brass-300 uppercase">Speak to us</p>
                <a href="tel:{{ $agent['phone_dial'] }}"
                    class="mt-3 block font-display text-2xl text-white transition hover:text-brass-200"
                    data-analytics="click-to-call-footer">{{ $agent['phone'] }}</a>
                <a href="tel:{{ preg_replace('/\s+/', '', $agent['office_phone']) }}"
                    class="mt-1 block text-sm transition hover:text-white">Office {{ $agent['office_phone'] }}</a>
                <a href="mailto:{{ $agent['email'] }}"
                    class="mt-1 block text-sm transition hover:text-white">{{ $agent['email'] }}</a>

                <a href="https://wa.me/{{ $agent['whatsapp'] }}" target="_blank" rel="noopener noreferrer"
                    class="mt-5 inline-flex items-center gap-2 rounded-full bg-[#25D366] px-5 py-2.5 text-sm font-semibold text-ink-950 transition hover:brightness-95">
                    <x-social-icon network="whatsapp" class="h-4 w-4" />
                    WhatsApp us
                </a>
            </div>
        </div>

        {{-- Navigation, office and hours --}}
        <div class="grid gap-10 border-t border-white/10 py-12 sm:grid-cols-2 lg:grid-cols-3">

            <nav aria-label="Footer navigation" class="sm:col-span-2 lg:col-span-1">
                <h2 class="text-[11px] font-semibold tracking-[0.18em] text-white uppercase">Explore</h2>
                <div class="mt-5 grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <ul class="space-y-3">
                        <li><a href="{{ route('for-sale') }}" class="transition hover:text-white">Properties for Sale</a></li>
                        <li><a href="{{ route('for-rent') }}" class="transition hover:text-white">Property to Rent</a></li>
                        <li><a href="{{ route('sold') }}" class="transition hover:text-white">Recently Sold</a></li>
                        <li><a href="{{ route('testimonials') }}" class="transition hover:text-white">Reviews</a></li>
                        <li><a href="{{ route('blog') }}" class="transition hover:text-white">Blog</a></li>
                        <li><a href="{{ route('contact') }}" class="transition hover:text-white">Contact</a></li>
                    </ul>
                    <ul class="space-y-3">
                        <li><a href="{{ route('buying') }}" class="transition hover:text-white">Buying Guide</a></li>
                        <li><a href="{{ route('rentals') }}" class="transition hover:text-white">Renting Guide</a></li>
                        <li><a href="{{ route('selling') }}" class="transition hover:text-white">Selling Guide</a></li>
                        <li><a href="{{ route('management') }}" class="transition hover:text-white">Property Management</a></li>
                        <li><a href="{{ route('about') }}" class="transition hover:text-white">About</a></li>
                    </ul>
                </div>
            </nav>

            <div>
                <h2 class="text-[11px] font-semibold tracking-[0.18em] text-white uppercase">Office</h2>
                <address class="mt-5 text-sm leading-relaxed not-italic">
                    {{ $agent['office']['street'] }}<br>
                    {{ $agent['office']['suburb'] }}, {{ $agent['office']['state'] }}@if (filled($agent['office']['postcode'])) {{ $agent['office']['postcode'] }}@endif
                </address>
                <a href="{{ $directions }}" target="_blank" rel="noopener noreferrer"
                    class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brass-300 transition hover:text-brass-200">
                    Get directions
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M9 7h8v8" />
                    </svg>
                </a>
            </div>

            <div>
                <h2 class="text-[11px] font-semibold tracking-[0.18em] text-white uppercase">Office hours</h2>
                <dl class="mt-5 space-y-2.5 text-sm">
                    @foreach ($agent['hours'] as $days => $hours)
                    <div class="flex justify-between gap-4 border-b border-white/5 pb-2.5 last:border-0 last:pb-0">
                        <dt>{{ $days }}</dt>
                        <dd class="text-right text-sand-200">{{ $hours }}</dd>
                    </div>
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- Areas: a reference line, not a paragraph of links --}}
        <div class="border-t border-white/10 py-8">
            <h2 class="text-[11px] font-semibold tracking-[0.18em] text-white uppercase">Where we work</h2>
            <p class="mt-3 text-sm leading-relaxed">{{ implode(' · ', $agent['service_areas']) }}</p>
        </div>

        {{-- Legal --}}
        <div class="flex flex-col gap-4 border-t border-white/10 py-6 text-xs sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ date('Y') }} {{ $agent['agency'] }}.@if (filled($agent['license'])) {{ $agent['license'] }}. @endif</p>
            <nav aria-label="Footer legal" class="flex flex-wrap items-center gap-5">
                <a href="{{ route('privacy') }}" class="transition hover:text-white">Privacy</a>
                <a href="{{ route('sitemap') }}" class="transition hover:text-white">Sitemap</a>
                <a href="{{ route('admin.dashboard') }}" class="transition hover:text-white">Admin</a>
                <a href="#main" class="inline-flex items-center gap-1.5 transition hover:text-white">
                    Back to top
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M6 11l6-6 6 6" />
                    </svg>
                </a>
            </nav>
        </div>
    </div>
</footer>