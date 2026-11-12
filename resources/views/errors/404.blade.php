@extends('layouts.app', [
'title' => 'Page not found',
'description' => 'That page is no longer here. Browse current listings or get in touch.',
'robots' => 'noindex, follow',
])

@section('content')

<section class="hero-under-bar relative isolate overflow-hidden bg-ink-900 text-sand-100">
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="absolute -top-32 left-1/4 h-72 w-72 rounded-full bg-brass-500/25 blur-3xl"></div>
        <div class="absolute -bottom-44 -right-20 h-80 w-80 rounded-full bg-indigo-500/20 blur-3xl"></div>
    </div>

    <div class="container-page py-20 text-center lg:py-28">
        <p class="font-display text-6xl text-white/15 sm:text-7xl">404</p>
        <h1 class="mt-2 text-4xl text-white sm:text-5xl">That page has moved on.</h1>
        <p class="mx-auto mt-5 max-w-xl text-[17px] leading-relaxed text-sand-200">
            The listing may have sold, or the address may be mistyped. Everything currently
            on the market is one click away.
        </p>

        <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="{{ route('for-sale') }}" class="btn-accent">Browse properties for sale</a>
            <a href="{{ route('contact') }}" class="btn-ghost-light">Get in touch</a>
        </div>
    </div>
</section>

<section class="container-page py-16 lg:py-20">
    <x-section-heading eyebrow="Try one of these" title="Popular pages" align="center" />
    <ul class="mx-auto mt-10 grid max-w-3xl gap-4 sm:grid-cols-2">
        @foreach ([
        ['Properties for Sale', route('for-sale'), 'Everything currently on the market'],
        ['Recently Sold', route('sold'), 'What comparable property actually transferred for'],
        ['Selling & Free Appraisal', route('selling').'#appraisal', 'Find out what your place is worth'],
        ['About', route('about'), 'Who you would be dealing with'],
        ] as [$label, $url, $blurb])
        <li>
            <a href="{{ $url }}" class="block rounded-2xl border border-ink-100 bg-white p-5 transition duration-200 hover:-translate-y-0.5 hover:border-ink-300 hover:shadow-lg hover:shadow-ink-950/5">
                <p class="font-sans font-semibold text-ink-900">{{ $label }}</p>
                <p class="mt-1 text-sm text-ink-500">{{ $blurb }}</p>
            </a>
        </li>
        @endforeach
    </ul>
</section>

@endsection