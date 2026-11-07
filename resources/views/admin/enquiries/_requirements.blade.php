{{-- What the caller asked for, and the listings that fit it now. --}}
@php
$labels = [
'intent' => 'Looking to', 'property_types' => 'Type', 'areas' => 'Areas', 'budget_min' => 'Budget from',
'budget_max' => 'Budget up to', 'bedrooms_min' => 'Bedrooms (min)', 'land_marla_min' => 'Size (min, sq ft)',
'timeline' => 'Timeline', 'payment' => 'Payment', 'appointment_requested' => 'Wants a viewing',
'preferred_time' => 'Preferred time', 'property_of_interest' => 'Asked about', 'selling_property' => 'Property to sell', 'notes' => 'Notes',
];
$show = fn ($key, $value) => match (true) {
in_array($key, ['budget_min', 'budget_max'], true) => \App\Support\Format::price((int) $value),
$key === 'property_types' => collect($value)->map(fn ($t) => config('agent.property_types.'.$t, $t))->implode(', '),
is_array($value) => implode(', ', $value),
is_bool($value) => $value ? 'Yes' : 'No',
default => str_replace('_', ' ', (string) $value),
};
@endphp
<div class="mt-6 rounded-lg border border-ink-100 p-4">
    <h3 class="text-sm font-semibold text-ink-900">What they are looking for</h3>
    <dl class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach (array_intersect_key($requirements, $labels) as $key => $value)
        <div>
            <dt class="text-xs tracking-wide text-ink-400 uppercase">{{ $labels[$key] }}</dt>
            <dd class="mt-0.5 text-sm text-ink-800">{{ $show($key, $value) }}</dd>
        </div>
        @endforeach
    </dl>

    @if ($matches && $matches['properties'])
    <h3 class="mt-5 text-sm font-semibold text-ink-900">
        {{ $matches['exact'] ? 'Listings that fit' : 'Nothing fits exactly - closest listings' }}
    </h3>
    <ul class="mt-2 space-y-1.5 text-sm">
        @foreach ($matches['properties'] as $match)
        <li>
            <a href="{{ $match['url'] }}" target="_blank" class="font-medium text-brass-600 hover:underline">{{ $match['title'] }}</a>
            <span class="text-ink-500">&middot; {{ $match['price'] }} &middot; {{ $match['fit'] }}</span>
        </li>
        @endforeach
    </ul>
    @elseif ($matches)
    <p class="mt-4 text-sm text-ink-500">No current listing fits what they asked for.</p>
    @endif
</div>