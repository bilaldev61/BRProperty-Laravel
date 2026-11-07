@extends('layouts.app', [
'nav' => 'for-sale',
'title' => 'Properties for Sale',
'description' => 'Villas, apartments, townhouses and plots for sale with '.config('agent.agency').' across Dubai - Downtown, Business Bay, Dubai Marina, Arabian Ranches and Dubai Hills. Filter by area, price, bedrooms and property type.',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'Properties for Sale', 'url' => null],
],
'schema' => [\App\Support\Seo::itemList($properties->getCollection(), 'Properties for sale')],
])

@section('content')

<x-page-hero eyebrow="On the market" title="Properties for sale">
    <p class="max-w-2xl text-[17px] leading-relaxed text-sand-200">
        {{ $properties->total() }} {{ Str::plural('property', $properties->total()) }} currently available.
        Not seeing it? Off-market properties come up regularly &mdash;
        <a href="{{ route('selling') }}" class="text-brass-300 underline underline-offset-4 hover:text-brass-200">see how our selling service works</a>
        or <a href="{{ route('contact') }}" class="text-brass-300 underline underline-offset-4 hover:text-brass-200">tell us what you're after</a>.
    </p>
</x-page-hero>

<section class="container-page py-12">
    <x-property-filter
        :action="route('for-sale')"
        :suburbs="$suburbs"
        :filters="$filters"
        :types="config('agent.property_types')"
        :price-bands="config('agent.price_bands')" />

    @if ($properties->isEmpty())
    <div class="mt-10">
        <x-empty-state
            title="No properties match those filters"
            message="Try widening the price range or clearing the suburb filter. New listings are added most weeks."
            :action-url="route('for-sale')"
            action-label="Clear filters" />
    </div>
    @else
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($properties as $property)
        <x-property-card :property="$property" :eager="$loop->index < 3" />
        @endforeach
    </div>

    <div class="mt-12">
        {{ $properties->links() }}
    </div>
    @endif
</section>

@endsection