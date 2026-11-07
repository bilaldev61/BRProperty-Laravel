@props(['property'])

{{--
    The rental equivalent of the status chip. Colour is fixed across the site:
      available   -> indigo  (open to let)
      let_agreed  -> brass   (offer accepted, not yet signed)
      let         -> ink     (tenanted / closed)
    A chip always carries its label, so colour is never the only signal.
--}}
@php
$tones = [
'available' => ['bg-indigo-100 text-indigo-700', 'To Let'],
'let_agreed' => ['bg-brass-200 text-brass-700', 'Let Agreed'],
'let' => ['bg-ink-800 text-sand-100', 'Let'],
];
[$classes, $label] = $tones[$property->rent_status] ?? ['bg-ink-100 text-ink-600', $property->rentStatusLabel()];
@endphp

<span {{ $attributes->class(['chip', $classes]) }}>
  <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true"></span>
  {{ $label }}
</span>