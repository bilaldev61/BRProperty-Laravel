@props(['label', 'value', 'meta' => null, 'delta' => null, 'tone' => 'ink', 'href' => null])

@php
$tones = [
'brass' => 'text-brass-700 before:bg-brass-500',
'teal' => 'text-teal-700 before:bg-teal-500',
'indigo' => 'text-indigo-700 before:bg-indigo-500',
'clay' => 'text-clay-700 before:bg-clay-500',
'ink' => 'text-ink-700 before:bg-ink-400',
];

$tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'relative block overflow-hidden rounded-xl border border-ink-200/70 bg-white p-5 shadow-sm shadow-ink-950/5',
        'before:absolute before:inset-y-0 before:left-0 before:w-0.5' => true,
        'transition hover:border-ink-300 hover:shadow-md' => $href,
        $tones[$tone] ?? $tones['ink'],
    ]) }}>
    <p class="text-[11px] font-semibold tracking-[0.14em] text-ink-500 uppercase">{{ $label }}</p>

    <p class="mt-2 font-sans text-3xl font-semibold tracking-tight text-ink-900 tabular-nums">{{ $value }}</p>

    <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
        @if ($delta)
        <span @class([ 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold' , 'bg-emerald-50 text-emerald-800'=> $delta['direction'] === 'up',
            'bg-red-50 text-red-800' => $delta['direction'] === 'down',
            'bg-ink-100 text-ink-600' => $delta['direction'] === 'flat',
            ])>
            {{-- An arrow as well as colour: direction is never colour alone. --}}
            @if ($delta['direction'] === 'up')
            <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                <path d="M6 2 10 8H2z" />
            </svg>
            @elseif ($delta['direction'] === 'down')
            <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                <path d="M6 10 2 4h8z" />
            </svg>
            @endif
            {{ $delta['value'] }}
        </span>
        <span class="text-ink-500">{{ $delta['caption'] }}</span>
        @elseif ($meta)
        <span class="text-ink-500">{{ $meta }}</span>
        @endif
    </div>

    @if ($delta && $meta)
    <p class="mt-1 text-xs text-ink-400">{{ $meta }}</p>
    @endif
</{{ $tag }}>