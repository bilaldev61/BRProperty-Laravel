@props(['tone' => 'ink'])

@php
// One place for the chip colours, so a role, a status and a count all look
// like they belong to the same system.
$tones = [
'brass' => 'bg-brass-50 text-brass-700 ring-brass-200',
'teal' => 'bg-teal-50 text-teal-700 ring-teal-200',
'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
'clay' => 'bg-clay-50 text-clay-700 ring-clay-200',
'ochre' => 'bg-ochre-50 text-ochre-700 ring-ochre-200',
'ink' => 'bg-ink-100 text-ink-600 ring-ink-200',
'red' => 'bg-red-50 text-red-700 ring-red-200',
'green' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap ring-1 ring-inset',
    $tones[$tone] ?? $tones['ink'],
]) }}>
    {{ $slot }}
</span>