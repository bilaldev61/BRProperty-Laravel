@extends('layouts.app', [
'nav' => 'for-rent',
'title' => 'Property to Rent',
'description' => 'Villas, apartments and townhouses to rent with '.config('agent.agency').' across Dubai - Business Bay, Dubai Marina, JLT, JVC and Dubai Hills. Filter by area, annual rent, bedrooms and property type.',
'breadcrumbs' => [
['name' => 'Home', 'url' => route('home')],
['name' => 'For Rent', 'url' => null],
],
'schema' => [\App\Support\Seo::itemList($properties->getCollection(), 'Properties to rent')],
])

@section('content')

<x-page-hero eyebrow="To rent" title="Properties to rent">
    <p class="max-w-2xl text-[17px] leading-relaxed text-sand-200">
        {{ $properties->total() }} {{ Str::plural('rental', $properties->total()) }} currently available.
        Not seeing it? We place rentals off-market too &mdash;
        <a href="{{ route('rentals') }}" class="text-brass-300 underline underline-offset-4 hover:text-brass-200">see how our rentals service works</a>
        or <a href="{{ route('contact') }}" class="text-brass-300 underline underline-offset-4 hover:text-brass-200">tell us what you're after</a>.
    </p>
</x-page-hero>

<section class="container-page py-12">
    <x-property-filter
        :action="route('for-rent')"
        :suburbs="$suburbs"
        :filters="$filters"
        :types="config('agent.property_types')"
        :price-bands="config('agent.rent_bands')"
        price-label="Annual rent"
        :rent="true" />

    @if ($properties->isEmpty())
    <div class="mt-10">
        <x-empty-state
            title="No rentals match those filters"
            message="Try widening the rent range or clearing the area filter. New rentals are added most weeks &mdash; tell us what you're after and we'll watch for it."
            :action-url="route('for-rent')"
            action-label="Clear filters" />
    </div>
    @else
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($properties as $property)
        <x-property-card :property="$property" context="rent" :eager="$loop->index < 3" />
        @endforeach
    </div>

    <div class="mt-12">
        {{ $properties->links() }}
    </div>
    @endif
</section>

@endsection