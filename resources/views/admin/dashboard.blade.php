@php
/*
* One screen, four jobs. Each band is drawn only when the controller supplied
* its data, and the flags behind that are the same abilities that guard the
* routes: an agent answers enquiries and so sees them here, an editor keeps
* listings tidy and so sees listings here, and neither sees what the portfolio
* is worth. Nothing hidden by a flag is ever sent to the browser.
*/
$columns = match (count($tiles)) {
1 => 'sm:grid-cols-1',
2 => 'sm:grid-cols-2',
3 => 'sm:grid-cols-3',
default => 'sm:grid-cols-2 xl:grid-cols-4',
};

$tileTones = ['teal', 'brass', 'indigo', 'clay'];
$stageDots = ['bg-teal-500', 'bg-brass-500', 'bg-ink-700', 'bg-ink-300'];

$intro = 'Where the work stands on ' . now()->format('l j F')
. ($seesEnquiries ? '. Numbers first, then what needs an answer.' : '.');
@endphp

<x-layouts.admin title="Dashboard" heading="Dashboard" :intro="$intro">
    <x-slot:actions>
        @can('properties.manage')
        <a href="{{ route('admin.properties.create') }}" class="btn-primary !px-4 !py-2 text-xs">+ New property</a>
        @endcan
    </x-slot:actions>

    {{-- Headline figures. One number each, because a tile is a question answered. --}}
    <section aria-label="Key figures" class="grid gap-4 {{ $columns }}">
        @foreach ($tiles as $i => $tile)
        <x-admin.stat
            :label="$tile['label']"
            :value="$tile['value']"
            :meta="$tile['meta']"
            :delta="$tile['delta']"
            :tone="$tileTones[$i % count($tileTones)]" />
        @endforeach
    </section>

    {{-- Pipeline: where every listing currently sits. --}}
    <section aria-label="Pipeline" class="overflow-hidden rounded-xl border border-ink-200/70 bg-white shadow-sm shadow-ink-950/5">
        <div class="flex flex-wrap divide-ink-100 sm:divide-x">
            @foreach ($pipeline as $i => $stage)
            <div class="min-w-[9rem] flex-1 px-5 py-4">
                <p class="flex items-center gap-1.5 text-[11px] font-semibold tracking-[0.12em] text-ink-500 uppercase">
                    <span class="h-2 w-2 rounded-full {{ $stageDots[$i] ?? 'bg-ink-300' }}" aria-hidden="true"></span>
                    {{ $stage['label'] }}
                </p>
                <p class="mt-1 font-sans text-xl font-semibold text-ink-900 tabular-nums">{{ $stage['value'] }}</p>
            </div>
            @endforeach
        </div>
    </section>

    @if ($seesEnquiries)
    <div class="grid gap-6 xl:grid-cols-3">
        <x-admin.card title="Enquiries per week"
            :description="'Last ' . \App\Support\DashboardMetrics::WEEKS . ' weeks, split by what people asked about.'"
            class="xl:col-span-2">
            <x-slot:actions>
                <a href="{{ route('admin.enquiries.index') }}" class="text-sm font-medium text-brass-700 hover:underline">Open inbox</a>
            </x-slot:actions>

            <x-island
                name="StackedBarChart"
                :props="['series' => $enquiriesByWeek['series'], 'points' => $enquiriesByWeek['points'], 'height' => 250]">
                {{-- Server-rendered fallback: the numbers, before Vue mounts --}}
                <p class="py-10 text-center text-sm text-ink-400">
                    {{ collect($enquiriesByWeek['points'])->sum(fn ($p) => array_sum($p['values'])) }} enquiries
                    over {{ count($enquiriesByWeek['points']) }} weeks.
                </p>
            </x-island>
        </x-admin.card>

        <x-admin.card title="Most enquired listings" description="Enquiries received in the last 90 days.">
            <x-island name="HBarChart" :props="['rows' => $topListings, 'slot' => 1,
                'emptyMessage' => 'No enquiries against a listing yet.']">
                <ul class="space-y-2 text-sm text-ink-600">
                    @foreach ($topListings as $row)
                    <li class="flex justify-between gap-3"><span class="truncate">{{ $row['label'] }}</span><span>{{ $row['value'] }}</span></li>
                    @endforeach
                </ul>
            </x-island>
        </x-admin.card>
    </div>
    @endif

    @if ($seesValue || $seesEnquiries)
    <div class="grid gap-6 xl:grid-cols-3">
        @if ($seesValue)
        <x-admin.card title="Portfolio value by area" description="Asking prices of listings currently on the market.">
            <x-island name="HBarChart" :props="['rows' => $valueByArea, 'slot' => 3,
                'emptyMessage' => 'No priced listings on the market.']">
                <ul class="space-y-2 text-sm text-ink-600">
                    @foreach ($valueByArea as $row)
                    <li class="flex justify-between gap-3"><span>{{ $row['label'] }}</span><span>{{ $row['formatted'] }}</span></li>
                    @endforeach
                </ul>
            </x-island>
        </x-admin.card>
        @endif

        @if ($seesEnquiries)
        <x-admin.card title="Latest enquiries" :padded="false" class="{{ $seesValue ? 'xl:col-span-2' : 'xl:col-span-3' }}">
            <x-slot:actions>
                <a href="{{ route('admin.enquiries.index') }}" class="text-sm font-medium text-brass-700 hover:underline">View all</a>
            </x-slot:actions>

            @forelse ($recentEnquiries as $enquiry)
            <a href="{{ route('admin.enquiries.show', $enquiry) }}"
                class="flex items-start justify-between gap-4 border-b border-ink-50 px-5 py-3.5 last:border-0 hover:bg-sand-50">
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 font-medium text-ink-900">
                        @unless ($enquiry->isRead())
                        <span class="h-2 w-2 shrink-0 rounded-full bg-brass-500" title="Unread"></span>
                        @endunless
                        {{ $enquiry->name }}
                        <x-admin.badge>{{ $enquiry->typeLabel() }}</x-admin.badge>
                    </p>
                    <p class="mt-0.5 truncate text-sm text-ink-500">
                        {{ $enquiry->property?->title ?? $enquiry->message ?? $enquiry->email }}
                    </p>
                </div>
                <span class="shrink-0 text-xs text-ink-400">{{ $enquiry->created_at->diffForHumans(short: true) }}</span>
            </a>
            @empty
            <p class="px-5 py-10 text-center text-sm text-ink-400">No enquiries yet.</p>
            @endforelse
        </x-admin.card>
        @endif
    </div>
    @endif

    <x-admin.card title="Recently added listings" :padded="false">
        <x-slot:actions>
            <a href="{{ route('admin.properties.index') }}" class="text-sm font-medium text-brass-700 hover:underline">
                {{ $canEditListings ? 'Manage listings' : 'All listings' }}
            </a>
        </x-slot:actions>

        <div class="grid gap-px bg-ink-50 sm:grid-cols-2 xl:grid-cols-4">
            @forelse ($latestProperties as $property)
            {{-- Without properties.manage a card opens the live listing rather
            than an editor this reader would be refused. --}}
            <a href="{{ $canEditListings ? route('admin.properties.edit', $property) : route('properties.show', $property) }}"
                @unless ($canEditListings) target="_blank" rel="noopener" @endunless
                class="group bg-white p-4 hover:bg-sand-50">
                <img src="{{ $property->heroImageUrl() }}" alt="" loading="lazy" decoding="async" class="aspect-4/3 w-full rounded-md object-cover">
                <p class="mt-3 truncate text-sm font-medium text-ink-900">{{ $property->title }}</p>
                <p class="mt-0.5 text-sm text-ink-500">{{ $property->priceDisplay() }}</p>
                <x-status-chip :status="$property->status" class="mt-2 !text-[11px]" />
            </a>
            @empty
            <p class="bg-white px-5 py-10 text-center text-sm text-ink-400 sm:col-span-2 xl:col-span-4">No properties yet.</p>
            @endforelse
        </div>
    </x-admin.card>
</x-layouts.admin>