@props(['name' => 'envelope', 'tone' => 'brass'])

@php
/*
* One icon per row of the admin bell, in the same rounded chip the sidebar uses.
* Tones are spelled out rather than assembled from the name, because Tailwind
* only emits classes it can see written in a file.
*/
$tones = [
'brass' => 'bg-brass-50 text-brass-700',
'teal' => 'bg-teal-50 text-teal-700',
'clay' => 'bg-clay-50 text-clay-700',
'indigo' => 'bg-indigo-50 text-indigo-700',
];
@endphp

<span data-icon="{{ $name }}"
    {{ $attributes->class(['inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg', $tones[$tone] ?? $tones['brass']]) }}
    aria-hidden="true">
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
        stroke-linecap="round" stroke-linejoin="round">
        @switch($name)
        @case('house')
        <path d="M3 10.5 12 3l9 7.5" />
        <path d="M5.5 9.5V21h13V9.5" />
        <path d="M10 21v-6h4v6" />
        @break

        @case('appraisal')
        <path d="M9 4h6v3H9z" />
        <path d="M9 5.5H6.5A1.5 1.5 0 0 0 5 7v12.5A1.5 1.5 0 0 0 6.5 21h11a1.5 1.5 0 0 0 1.5-1.5V7a1.5 1.5 0 0 0-1.5-1.5H15" />
        <path d="M9 12h6M9 16h4" />
        @break

        @case('question')
        <path d="M20 12a8 8 0 1 0-3.2 6.4L21 20l-1.1-3.6A7.9 7.9 0 0 0 20 12Z" />
        <path d="M9.8 9.4a2.2 2.2 0 1 1 3.4 1.9c-.7.5-1.2.9-1.2 1.7" />
        <path d="M12 16.3h.01" />
        @break

        @default
        <rect x="3" y="5.5" width="18" height="13" rx="2" />
        <path d="m3.8 7 8.2 6 8.2-6" />
        @endswitch
    </svg>
</span>