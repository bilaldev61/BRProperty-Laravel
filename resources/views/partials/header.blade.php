@php
$agent = config('agent');
// Grouped navigation: Buy / Rent / Sell are dropdowns whose top label also links to
// the primary page; About and Contact are direct links. Each page sets its own
// `nav` key (see the views) and that key matches one child below, which drives the
// active highlighting for both the group and the item.
$menu = [
['key' => 'buy', 'label' => 'Buy', 'url' => route('for-sale'), 'children' => [
['key' => 'for-sale', 'label' => 'For Sale', 'url' => route('for-sale')],
['key' => 'sold', 'label' => 'Recently Sold', 'url' => route('sold')],
['key' => 'buying', 'label' => 'Buying Guide', 'url' => route('buying')],
]],
['key' => 'rent', 'label' => 'Rent', 'url' => route('for-rent'), 'children' => [
['key' => 'for-rent', 'label' => 'For Rent', 'url' => route('for-rent')],
['key' => 'rentals', 'label' => 'Renting Guide', 'url' => route('rentals')],
]],
['key' => 'sell', 'label' => 'Sell', 'url' => route('selling'), 'children' => [
['key' => 'selling', 'label' => 'Selling Guide', 'url' => route('selling')],
['key' => 'management', 'label' => 'Property Management', 'url' => route('management')],
]],
['key' => 'testimonials', 'label' => 'Reviews', 'url' => route('testimonials')],
['key' => 'blog', 'label' => 'Blog', 'url' => route('blog')],
['key' => 'about', 'label' => 'About', 'url' => route('about')],
['key' => 'contact', 'label' => 'Contact', 'url' => route('contact')],
];
$current = $nav ?? '';
$isGroupActive = fn ($group) => $current === $group['key']
|| collect($group['children'] ?? [])->pluck('key')->contains($current);

// The home and contact pages both pull a full-bleed photograph up behind the bar,
// so there the bar floats over the image. Every other page opens on a flat dark
// hero band and the bar sits on that same colour. Dark glass with light type in
// both cases, so the nav never changes colour as you move between pages. One set
// of classes, chosen here, keeps them from drifting apart.
$overlay = in_array($current, ['home', 'contact'], true);

// Dark enough that the labels stay legible once the bar is floating over a pale
// section on scroll, not only over the dark ground it starts on.
$pill = 'border-white/15 bg-ink-950/75 text-sand-50 shadow-ink-950/25';

$activeLink = 'bg-white/15 text-white';
$idleLink = 'text-sand-100 hover:bg-white/10 hover:text-white';

$muted = 'text-sand-300';
@endphp

{{--
    The bar itself is a floating pill rather than a full-width strip: inset from the
    edges, frosted, and pinned on scroll. It is dark glass on every page - over the
    home photograph, and inside the dark band the other pages open with - so the nav
    never changes colour as you move around the site.
--}}
<header @class(['sticky top-0 z-40 px-3 pt-3 pb-3 sm:px-5', 'bg-ink-900'=> ! $overlay])>
    <div @class([ 'mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-4 rounded-full border px-4 shadow-lg backdrop-blur-xl sm:gap-6 sm:px-6' ,
        $pill,
        ])>
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ $agent['agency'] }} home">
            <img src="{{ \App\Support\Media::agent('logo_mark') }}" alt="" width="36" height="36" class="h-9 w-9 shrink-0" aria-hidden="true">
            <span class="leading-tight whitespace-nowrap">
                <span class="block font-display text-lg leading-none tracking-tight">{{ $agent['agency'] }}</span>
                <span class="mt-1 block text-[9px] font-semibold tracking-[0.16em] uppercase {{ $muted }}">{{ $agent['descriptor'] }}</span>
            </span>
        </a>

        <nav class="hidden min-w-0 xl:block" aria-label="Main">
            <ul class="flex items-center gap-0.5 text-sm 2xl:gap-1">
                @foreach ($menu as $item)
                <li class="group relative">
                    @if (isset($item['children']))
                    <a href="{{ $item['url'] }}"
                        @class([ 'relative flex items-center gap-1 rounded-full px-3.5 py-2 font-medium whitespace-nowrap transition-colors' , $activeLink=> $isGroupActive($item),
                        $idleLink => ! $isGroupActive($item),
                        ])
                        @if ($isGroupActive($item)) aria-current="page" @endif
                        >
                        {{ $item['label'] }}
                        <svg class="h-3.5 w-3.5 opacity-75 transition-transform group-hover:rotate-180 group-focus-within:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                        </svg>
                        {{-- Invisible hover bridge so the panel stays open between the label and the dropdown --}}
                        <span class="absolute inset-x-0 top-full h-3" aria-hidden="true"></span>
                    </a>
                    <div class="invisible absolute left-1/2 top-full z-40 -translate-x-1/2 pt-3 opacity-0 transition-opacity duration-150 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                        <ul class="w-56 rounded-2xl border border-ink-100 bg-white p-1.5 text-ink-900 shadow-xl shadow-ink-950/10">
                            @foreach ($item['children'] as $child)
                            <li>
                                <a href="{{ $child['url'] }}"
                                    @class([ 'block rounded-xl px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors' , 'bg-ink-900 text-sand-50'=> $current === $child['key'],
                                    'text-ink-600 hover:bg-ink-900/5 hover:text-ink-900' => $current !== $child['key'],
                                    ])
                                    @if ($current === $child['key']) aria-current="page" @endif
                                    >{{ $child['label'] }}</a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @else
                    <a href="{{ $item['url'] }}"
                        @class([ 'relative block rounded-full px-3.5 py-2 font-medium whitespace-nowrap transition-colors' , $activeLink=> $current === $item['key'],
                        $idleLink => $current !== $item['key'],
                        ])
                        @if ($current === $item['key']) aria-current="page" @endif
                        >{{ $item['label'] }}</a>
                    @endif
                </li>
                @endforeach
            </ul>
        </nav>

        <div class="flex shrink-0 items-center gap-2">
            <a href="tel:{{ $agent['phone_dial'] }}"
                class="hidden items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold whitespace-nowrap text-ink-900 transition-colors hover:bg-brass-200 xl:inline-flex"
                data-analytics="click-to-call-header">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.2.4 2.4.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1l-2.3 2.2Z" />
                </svg>
                {{ $agent['phone'] }}
            </a>

            <x-island
                name="MobileNav"
                :props="[
                    'links' => $menu,
                    'phone' => $agent['phone'],
                    'phoneDial' => $agent['phone_dial'],
                    'appraisalUrl' => route('selling').'#appraisal',
                    'adminUrl' => route('admin.dashboard'),
                    'current' => $current,
                    'light' => true,
                ]">
                {{-- Fallback before Vue mounts: a plain link to the contact page --}}
                <a href="{{ route('contact') }}" class="flex h-10 w-10 items-center justify-center rounded-full text-sand-50 hover:bg-white/10 xl:hidden" aria-label="Menu">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" d="M3.5 7h17M3.5 12h17M3.5 17h17" />
                    </svg>
                </a>
            </x-island>
        </div>
    </div>
</header>