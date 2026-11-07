@props(['priority', 'reason' => null])

{{--
    How urgently this enquiry wants a call back. Colour is never the only
    signal - the chip always carries its word, and `reason` puts the rule behind
    the label in the tooltip so the agent can see why it was judged that way.
--}}
@php
    $tones = [
        'hot'  => ['bg-clay-100 text-clay-700',   'Hot'],
        'warm' => ['bg-brass-200 text-brass-700', 'Warm'],
        'cold' => ['bg-ink-100 text-ink-600',     'Cold'],
    ];
    [$classes, $label] = $tones[$priority] ?? ['bg-ink-100 text-ink-600', ucfirst((string) $priority)];
@endphp

<span {{ $attributes->class(['chip', $classes]) }} @if ($reason) title="{{ $reason }}" @endif>
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true"></span>
    {{ $label }}
</span>
