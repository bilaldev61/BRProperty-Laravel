@props(['type'])

@php
    $tones = [
        'house'     => 'bg-clay-100 text-clay-700',
        'apartment' => 'bg-indigo-100 text-indigo-700',
        'townhouse' => 'bg-indigo-100 text-indigo-700',
        'land'      => 'bg-teal-100 text-teal-700',
        'acreage'   => 'bg-clay-100 text-clay-700',
    ];
@endphp

<span {{ $attributes->class(['chip', $tones[$type] ?? 'bg-ink-100 text-ink-600']) }}>
    {{ config('agent.property_types.'.$type, ucfirst($type)) }}
</span>
