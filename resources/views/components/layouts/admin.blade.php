@props(['title' => 'Admin', 'heading' => null, 'intro' => null, 'actions' => null])

@php
$me = auth()->user();
$can = fn (?string $ability) => $me?->hasAbility($ability) ?? false;

/*
* The footer chip colours its dot from the role's own tone, so a role looks
* the same here as it does in the team screen. The classes are spelled out
* because Tailwind only emits what it can read as a literal.
*/
$roleDots = [
'brass' => 'bg-brass-400',
'indigo' => 'bg-indigo-400',
'teal' => 'bg-teal-400',
'clay' => 'bg-clay-400',
];

/*
* The sidebar is generated from one list, so a page cannot be reachable by
* URL but invisible in the menu. Items carry the ability that unlocks them;
* groups left empty by the current role disappear entirely rather than
* leaving a heading over nothing.
*/
$groups = [
['label' => null, 'items' => [
['route' => 'admin.dashboard', 'label' => 'Dashboard', 'match' => 'admin.dashboard', 'ability' => 'dashboard.view',
'icon' => 'M4 11 12 4l8 7v9h-5v-6H9v6H4z'],
]],
['label' => 'Inbox', 'items' => [
['route' => 'admin.enquiries.index', 'label' => 'Enquiries', 'match' => 'admin.enquiries.*', 'ability' => 'enquiries.view',
'icon' => 'M4 6h16v12H4zM4 7l8 6 8-6', 'badge' => 'outstanding'],
['route' => 'admin.appointments.index', 'label' => 'Appointments', 'match' => 'admin.appointments.*', 'ability' => 'appointments.view',
'icon' => 'M4 7h16v13H4zM4 11h16M9 3v4M15 3v4', 'badge' => 'toConfirm'],
['route' => 'admin.calls.index', 'label' => 'Calls', 'match' => 'admin.calls.*', 'ability' => 'calls.view',
'icon' => 'M7 4l3 3-2 2a10 10 0 0 0 5 5l2-2 3 3-2 3A14 14 0 0 1 4 6z'],
['route' => 'admin.assistant.index', 'label' => 'Assistant', 'match' => 'admin.assistant.*', 'ability' => 'assistant.view',
'icon' => 'M5 5h14v10H9l-4 4z'],
]],
['label' => 'Listings', 'items' => [
['route' => 'admin.properties.index', 'label' => 'Properties', 'match' => 'admin.properties.*', 'ability' => 'properties.view',
'icon' => 'M4 20V7l8-4 8 4v13M9 20v-6h6v6'],
['route' => 'admin.testimonials.index', 'label' => 'Reviews', 'match' => 'admin.testimonials.*', 'ability' => 'testimonials.manage',
'icon' => 'M12 4l2.3 4.7 5.2.7-3.8 3.6.9 5.1-4.6-2.5-4.6 2.5.9-5.1L4.5 9.4l5.2-.7z'],
]],
['label' => 'Agency', 'items' => [
['route' => 'admin.team.index', 'label' => 'Team', 'match' => 'admin.team.*', 'ability' => 'team.manage',
'icon' => 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM3 20v-1.5A4.5 4.5 0 0 1 7.5 14h3a4.5 4.5 0 0 1 4.5 4.5V20M17 5.5a3.5 3.5 0 0 1 0 6.6M19 20v-1.5a4.5 4.5 0 0 0-2-3.7'],
['route' => 'admin.settings.index', 'label' => 'Settings', 'match' => 'admin.settings.*', 'ability' => 'settings.manage',
'icon' => 'M4 7h9M17 7h3M4 17h5M13 17h7M15 4v6M9 14v6'],
]],
];

$groups = collect($groups)
->map(function (array $group) use ($can) {
$group['items'] = array_values(array_filter($group['items'], fn (array $item) => $can($item['ability'])));

return $group;
})
->filter(fn (array $group) => $group['items'] !== [])
->all();

// Counted only for roles that can see the screen: no query, and no number
// leaking to someone who has no business knowing it.
$badges = [
'outstanding' => $can('enquiries.view') ? \App\Models\Enquiry::needsReply()->count() : 0,
'toConfirm' => $can('appointments.view')
? \App\Models\Appointment::where('status', \App\Models\Appointment::REQUESTED)->where('starts_at', '>=', now())->count()
: 0,
];

$initials = $me
? \Illuminate\Support\Str::of($me->name)->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode('')
: '';
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Admin' }} | {{ config('agent.agency') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-sand-100" @if (session('status')) data-flash="{{ session('status') }}" @endif>

    <a href="#main"
        class="sr-only rounded-lg bg-ink-900 px-4 py-2 text-sm font-semibold text-white focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-[60]">
        Skip to content
    </a>

    <div class="lg:flex">

        {{-- Panel backdrop: only ever visible while the drawer is open. --}}
        <div data-drawer-backdrop class="fixed inset-0 z-40 hidden bg-ink-950/60 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

        {{-- The panel itself does not scroll: the nav in the middle does, so
        the brand and the account footer stay where they are however many
        sections a role has. --}}
        <aside id="admin-sidebar" aria-label="Admin menu"
            class="fixed inset-y-0 left-0 z-50 flex w-[16.5rem] -translate-x-full flex-col overflow-hidden bg-ink-950 text-ink-300 transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0">

            <div class="h-0.5 w-full shrink-0 bg-gradient-to-r from-teal-500 via-brass-500 to-clay-500" aria-hidden="true"></div>

            <a href="{{ route('admin.dashboard') }}" class="flex shrink-0 items-center gap-2.5 px-5 py-5">
                <img src="{{ \App\Support\Media::agent('logo_mark') }}" alt="" width="34" height="34"
                    class="h-8.5 w-8.5" aria-hidden="true">
                <span class="min-w-0">
                    <span class="block truncate font-display text-base leading-tight text-white">{{ config('agent.agency') }}</span>
                    <span class="block text-[10px] font-semibold tracking-[0.18em] text-ink-500 uppercase">Admin</span>
                </span>
            </a>

            <nav class="flex-1 overflow-y-auto overscroll-contain px-3 pb-4" aria-label="Admin sections">
                @foreach ($groups as $group)
                @if ($group['label'])
                <p class="mt-5 mb-1.5 px-2 text-[10px] font-semibold tracking-[0.16em] text-ink-600 uppercase">
                    {{ $group['label'] }}
                </p>
                @endif

                <ul class="space-y-0.5">
                    @foreach ($group['items'] as $item)
                    @php
                    $active = request()->routeIs($item['match']);
                    $count = $badges[$item['badge'] ?? ''] ?? 0;
                    @endphp
                    <li>
                        <a href="{{ route($item['route']) }}"
                            @if ($active) aria-current="page" @endif
                            @class([ 'group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors' , 'bg-white/12 text-white shadow-sm'=> $active,
                            'text-ink-300 hover:bg-white/6 hover:text-white' => ! $active,
                            ])>
                            <svg class="h-4.5 w-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="{{ $item['icon'] }}" />
                            </svg>
                            <span class="truncate">{{ $item['label'] }}</span>
                            @if ($count)
                            <span class="ml-auto rounded-full bg-brass-500 px-1.5 py-0.5 text-[10px] font-bold text-white tabular-nums"
                                title="{{ $count }} waiting">{{ $count }}</span>
                            @endif
                        </a>
                    </li>
                    @endforeach
                </ul>
                @endforeach
            </nav>

            {{-- Who you are signed in as, then the two ways out. Three
            differently-shaped rows (a solid chip, a bare text link, a plain
            link) read as clutter, so the role is a quiet label and both
            actions are the same pill. --}}
            <div class="shrink-0 border-t border-white/10 p-3">
                <a href="{{ route('admin.profile.edit') }}"
                    class="flex items-center gap-2.5 rounded-lg p-2 transition hover:bg-white/6">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/10 text-[11px] font-semibold tracking-wide text-white"
                        aria-hidden="true">{{ $initials }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-white">{{ $me?->name }}</span>
                        <span class="block truncate text-[11px] text-ink-400">{{ $me?->email }}</span>
                    </span>
                </a>

                @if ($me?->role)
                <div class="mt-1 px-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/6 px-2 py-0.5 text-[10px] font-semibold tracking-[0.08em] text-ink-200 uppercase ring-1 ring-inset ring-white/10">
                        <span class="h-1.5 w-1.5 rounded-full {{ $roleDots[$me->role->tone()] ?? 'bg-ink-400' }}"
                            aria-hidden="true"></span>
                        {{ $me->role->label() }}
                    </span>
                </div>
                @endif

                <div class="mt-2 flex gap-1.5">
                    <a href="{{ route('home') }}" target="_blank" rel="noopener"
                        class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-white/10 px-2 py-1.5 text-[11px] font-medium text-ink-300 transition hover:border-white/25 hover:bg-white/6 hover:text-white">
                        View site
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M9 7h8v8" />
                        </svg>
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}" class="flex-1">
                        @csrf
                        <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-white/10 px-2 py-1.5 text-[11px] font-medium text-ink-300 transition hover:border-white/25 hover:bg-white/6 hover:text-white">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">

            <header class="sticky top-0 z-30 border-b border-ink-200/70 bg-white/92 backdrop-blur">
                <div class="flex items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                    <button type="button" data-drawer-toggle aria-controls="admin-sidebar" aria-expanded="false"
                        aria-label="Open the menu"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-ink-200 text-ink-600 transition hover:bg-sand-100 lg:hidden">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" aria-hidden="true">
                            <path d="M4 7h16M4 12h16M4 17h16" />
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-[11px] font-semibold tracking-[0.12em] text-ink-400 uppercase">
                            <a href="{{ route('admin.dashboard') }}" class="hover:text-ink-700">Admin</a>
                            @if (! request()->routeIs('admin.dashboard'))
                            <span class="text-ink-300" aria-hidden="true">/</span>
                            <span class="text-ink-500">{{ $heading ?? $title }}</span>
                            @endif
                        </nav>
                        <h1 class="truncate font-display text-xl leading-tight text-ink-900 sm:text-2xl">{{ $heading ?? ($title ?? 'Admin') }}</h1>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <x-admin.notifications />
                        @isset($actions){{ $actions }}@endisset
                    </div>
                </div>

                @if ($intro)
                <p class="max-w-3xl px-4 pb-3 text-sm text-ink-500 sm:px-6 lg:px-8">{{ $intro }}</p>
                @endif
            </header>

            <main id="main" class="flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <div class="mx-auto max-w-[100rem] space-y-6">

                    @if (isset($errors) && $errors->any())
                    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <p class="font-semibold">Please fix the following:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>

        </div>
    </div>

</body>

</html>