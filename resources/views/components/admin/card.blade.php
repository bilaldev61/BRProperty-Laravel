@props(['title' => null, 'description' => null, 'padded' => true, 'tone' => null])

@php
// The accent rule across the top edge is the only colour a panel carries, so
// colour codes a section without shouting from inside every card.
$edges = [
'brass' => 'before:bg-brass-500',
'teal' => 'before:bg-teal-500',
'indigo' => 'before:bg-indigo-500',
'clay' => 'before:bg-clay-500',
'ochre' => 'before:bg-ochre-500',
'plum' => 'before:bg-plum-500',
];
@endphp

<section {{ $attributes->class([
    'relative overflow-hidden rounded-xl border border-ink-200/70 bg-white shadow-sm shadow-ink-950/5',
    'before:absolute before:inset-x-0 before:top-0 before:h-0.5' => $tone,
    $edges[$tone] ?? null,
]) }}>
    @if ($title || isset($actions))
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-ink-100 px-5 py-4">
        <div class="min-w-0">
            @if ($title)
            <h2 class="font-sans text-sm font-semibold text-ink-900">{{ $title }}</h2>
            @endif
            @if ($description)
            <p class="mt-1 text-sm text-ink-500">{{ $description }}</p>
            @endif
        </div>
        @isset($actions)
        <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
    @endif

    <div @class(['p-5 sm:p-6'=> $padded])>
        {{ $slot }}
    </div>

    @isset($footer)
    <div class="border-t border-ink-100 bg-sand-50/70 px-5 py-3">{{ $footer }}</div>
    @endisset
</section>