@props(['status'])

{{--
    Where an enquiry sits in the workflow. Distinct from x-status-chip, which is
    about a property being for sale or sold - these two never appear on the same
    object, so they stay separate rather than sharing one overloaded component.
--}}
@php
    $tones = [
        'new'         => ['bg-teal-100 text-teal-700',     'New'],
        'in_progress' => ['bg-indigo-100 text-indigo-700', 'In progress'],
        'replied'     => ['bg-ink-100 text-ink-600',       'Replied'],
        'closed'      => ['bg-ink-800 text-sand-100',      'Closed'],
    ];
    [$classes, $label] = $tones[$status] ?? ['bg-ink-100 text-ink-600', ucfirst(str_replace('_', ' ', (string) $status))];
@endphp

<span {{ $attributes->class(['chip', $classes]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true"></span>
    {{ $label }}
</span>
