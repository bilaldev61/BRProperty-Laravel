<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pt-24">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#161d21">

    {{-- The font CSS is render-blocking; warming the connection shaves the handshake --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.bunny.net">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @include('partials.seo', [
    'title' => $title ?? null,
    'description' => $description ?? null,
    'image' => $image ?? null,
    'imageAlt' => $imageAlt ?? null,
    'ogType' => $ogType ?? 'website',
    'breadcrumbs' => $breadcrumbs ?? [],
    'schema' => $schema ?? [],
    'robots' => $robots ?? null,
    ])

    {{-- Photo-led pages paint the hero image before anything else; telling the
         browser to fetch it during head parsing is the single biggest LCP win
         on those pages. Only the pages whose first viewport IS the photo. --}}
    @if (in_array($nav ?? '', ['home', 'contact']))
    <link rel="preload" as="image" href="{{ \App\Support\Media::agent('hero_image') }}" fetchpriority="high">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen pb-callbar xl:pb-0" @if (session('status')) data-flash="{{ session('status') }}" @endif>
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-ink-900 focus:px-4 focus:py-2 focus:text-sand-50">
        Skip to content
    </a>

    @include('partials.header', ['nav' => $nav ?? ''])

    <main id="main">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.floating-actions')
    @include('partials.assistant', ['property' => $assistantProperty ?? null])

    @if (filled(config('agent.seo.analytics_id')))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('agent.seo.analytics_id') }}"></script>
    <script data-analytics-id="{{ config('agent.seo.analytics_id') }}">
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());
        gtag('config', document.currentScript.dataset.analyticsId);
    </script>
    @endif
</body>

</html>