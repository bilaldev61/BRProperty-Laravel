@php
    use App\Support\Media;
    use App\Support\Seo;

    $agent = config('agent');

    $seoTitle = trim($title ?? '') !== ''
        ? $title.' | '.$agent['seo']['site_name']
        : $agent['seo']['site_name'];

    $seoDescription = $description ?? $agent['seo']['description'];
    $seoImage       = $image ?? Media::agent('photo');
    $seoImageAlt    = $imageAlt ?? ($agent['name'].', '.$agent['title'].' at '.$agent['agency']);

    /*
     * Canonical: the path plus the page number, and nothing else. Filter
     * combinations produce near-identical pages, so they all point back at the
     * unfiltered list rather than competing with it in the index.
     */
    $page = (int) request()->query('page', 1);
    $canonical = $canonical ?? url()->current().($page > 1 ? '?page='.$page : '');

    /*
     * A filtered result set is thin and duplicative. Let Google follow the links
     * out of it, but keep it out of the index.
     */
    $isFiltered = collect(request()->query())->except('page')->filter()->isNotEmpty();
    $robots = $robots ?? ($isFiltered ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1');

    $jsonLd = Seo::document(array_merge(
        [Seo::agent(), Seo::website()],
        [Seo::breadcrumbs($breadcrumbs ?? [])],
        $schema ?? [],
    ));
@endphp

<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
@if (filled($agent['seo']['keywords']))
    <meta name="keywords" content="{{ $agent['seo']['keywords'] }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="{{ $robots }}">
<meta name="author" content="{{ $agent['name'] }}">

{{-- Local signals: helps Google tie the pages to a place --}}
@if ($agent['office']['lat'])
    <meta name="geo.position" content="{{ $agent['office']['lat'] }};{{ $agent['office']['lng'] }}">
    <meta name="ICBM" content="{{ $agent['office']['lat'] }}, {{ $agent['office']['lng'] }}">
@endif
<meta name="geo.region" content="{{ $agent['office']['country'] }}-{{ $agent['office']['state'] }}">
<meta name="geo.placename" content="{{ $agent['office']['suburb'] }}">

{{-- Open Graph --}}
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:site_name" content="{{ $agent['seo']['site_name'] }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ $agent['seo']['locale'] }}">
@if ($seoImage)
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:alt" content="{{ $seoImageAlt }}">
    <meta property="og:image:width" content="{{ $imageWidth ?? 1200 }}">
    <meta property="og:image:height" content="{{ $imageHeight ?? 630 }}">
@endif

{{-- X / Twitter --}}
<meta name="twitter:card" content="summary_large_image">
@if (filled($agent['seo']['twitter']))
    <meta name="twitter:site" content="{{ $agent['seo']['twitter'] }}">
@endif
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
@if ($seoImage)
    <meta name="twitter:image" content="{{ $seoImage }}">
    <meta name="twitter:image:alt" content="{{ $seoImageAlt }}">
@endif

{{-- Search Console / Bing ownership --}}
@if (filled($agent['seo']['google_verification'] ?? null))
    <meta name="google-site-verification" content="{{ $agent['seo']['google_verification'] }}">
@endif
@if (filled($agent['seo']['bing_verification'] ?? null))
    <meta name="msvalidate.01" content="{{ $agent['seo']['bing_verification'] }}">
@endif

{{-- One connected graph: the business, the site, the page and its listings --}}
<script type="application/ld+json">{!! $jsonLd !!}</script>

@stack('schema')
