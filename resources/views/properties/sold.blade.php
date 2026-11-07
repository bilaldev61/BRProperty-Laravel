@extends('layouts.app', [
'nav' => 'sold',
'title' => 'Recently Sold',
'description' => 'Recent sales handled by '.config('agent.agency').'. See what property across Dubai actually transferred for, and how long it took.',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'Recently Sold', 'url' => null],
],
'schema' => [\App\Support\Seo::itemList($properties->getCollection(), 'Recently sold')],
])

@section('content')

<x-page-hero eyebrow="Results" title="Recently sold">
    <p class="max-w-2xl text-[17px] leading-relaxed text-sand-200">
        The best guide to what an agency will get for your property is what they got for
        somebody else's. Here is every recent transfer, with the price and the time it took.
    </p>

    <dl class="grid grid-cols-1 gap-8 border-t border-white/10 pt-8 sm:grid-cols-3">
        <div>
            <dd class="font-display text-4xl text-white">{{ number_format($stats['count']) }}</dd>
            <dt class="mt-1 text-sm text-sand-300">Properties sold</dt>
        </div>
        <div>
            <dd class="font-display text-4xl text-white">{{ $stats['avgDays'] }} days</dd>
            <dt class="mt-1 text-sm text-sand-300">Average time on market</dt>
        </div>
        <div>
            <dd class="font-display text-4xl text-white">{{ \App\Support\Format::price((int) round($stats['totalValue'])) }}</dd>
            <dt class="mt-1 text-sm text-sand-300">Total value sold</dt>
        </div>
    </dl>
</x-page-hero>

<section class="container-page py-12">
    <x-property-filter
        :action="route('sold')"
        :suburbs="$suburbs"
        :filters="$filters"
        :types="config('agent.property_types')"
        :price-bands="config('agent.price_bands')"
        :show-price="false"
        :show-sort="false" />

    @if ($properties->isEmpty())
    <div class="mt-10">
        <x-empty-state title="No sales match those filters" :action-url="route('sold')" action-label="Clear filters" />
    </div>
    @else
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($properties as $property)
        <x-property-card :property="$property" :eager="$loop->index < 3" />
        @endforeach
    </div>

    <div class="mt-12">{{ $properties->links() }}</div>
    @endif
</section>

<section class="container-page pb-20">
    <div class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-ink-900 via-ink-800 to-ink-700 p-8 text-center text-sand-100 sm:p-12">
        <div class="absolute -right-16 -top-16 -z-10 h-56 w-56 rounded-full bg-brass-500/25 blur-3xl" aria-hidden="true"></div>
        <h2 class="text-3xl text-white sm:text-4xl">Want a number for your place?</h2>
        <p class="mx-auto mt-4 max-w-xl text-ink-300">A free valuation takes about an hour and comes with the comparable transfers attached.</p>
        <a href="{{ route('selling') }}#appraisal" class="btn-accent mt-8">Book a free valuation</a>
    </div>
</section>

@endsection