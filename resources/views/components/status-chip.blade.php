@props(['status'])

{{--
    Status colour is semantic and fixed across the whole site:
      for sale    -> teal    (active)
      under offer -> brass   (attention)
      sold        -> ink     (closed)
    A chip always carries its label, so colour is never the only signal.
--}}
@php
$tones = [
'for_sale' => ['bg-teal-100 text-teal-700', 'For Sale'],
'under_offer' => ['bg-brass-200 text-brass-700', 'Under Offer'],
'sold' => ['bg-ink-800 text-sand-100', 'Sold'],
'rental' => ['bg-indigo-100 text-indigo-700', 'To Rent'],
];
[$classes, $label] = $tones[$status] ?? ['bg-ink-100 text-ink-600', ucfirst(str_replace('_', ' ', $status))];
@endphp

<span {{ $attributes->class(['chip', $classes]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true"></span>
    {{ $label }}
</span>