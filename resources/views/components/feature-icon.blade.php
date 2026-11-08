@props(['name'])

@switch($name)
@case('bed')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path stroke-linecap="round" d="M3 18v-7m0 0V6m0 5h18m0 0v7m0-7V9a2 2 0 0 0-2-2h-6v4" />
    <circle cx="7.5" cy="9" r="1.8" />
</svg>
@break
@case('bath')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path stroke-linecap="round" d="M3 11h18v3a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5v-3Z" />
    <path stroke-linecap="round" d="M6 11V6a2 2 0 0 1 4 0M6 19l-1 2m14-2 1 2" />
</svg>
@break
@case('car')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2m16-2v2M3 16v-4l2-5h14l2 5v4H3Z" />
    <circle cx="7.5" cy="13.5" r="1" />
    <circle cx="16.5" cy="13.5" r="1" />
</svg>
@break
@case('land')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4z" />
    <path stroke-linecap="round" d="M4 10h16M9 6v12" />
</svg>
@break
@case('area')
{{-- Floor-plan measure: an outline with dimension ticks. --}}
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path stroke-linejoin="round" d="M4 4h16v16H4z" />
    <path stroke-linecap="round" d="M12 4v3m0 10v3M4 12h3m10 0h3" />
</svg>
@break
@case('metro')
{{-- Train front: the landmark buyers screen a listing on first. --}}
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <rect x="5" y="3" width="14" height="14" rx="3" />
    <path stroke-linecap="round" d="M5 10h14M9 21l2-4m4 4-2-4" />
    <circle cx="9.5" cy="13.5" r=".9" />
    <circle cx="14.5" cy="13.5" r=".9" />
</svg>
@break
@case('mall')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path stroke-linejoin="round" d="M5 8h14l-1 12H6L5 8Z" />
    <path stroke-linecap="round" d="M9 8V6a3 3 0 0 1 6 0v2" />
</svg>
@break
@case('school')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path stroke-linejoin="round" d="M3 9l9-4 9 4-9 4-9-4Z" />
    <path stroke-linecap="round" d="M7 11.5V16c0 1.7 2.2 3 5 3s5-1.3 5-3v-4.5" />
</svg>
@break
@case('hospital')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <rect x="4" y="4" width="16" height="16" rx="2" />
    <path stroke-linecap="round" d="M12 8v8M8 12h8" />
</svg>
@break
@case('beach')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <circle cx="12" cy="7" r="3" />
    <path stroke-linecap="round" d="M3 16c2 2 4 2 6 0s4-2 6 0 4 2 6 0M3 20c2 2 4 2 6 0s4-2 6 0 4 2 6 0" />
</svg>
@break
@case('park')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path stroke-linejoin="round" d="M12 3l5 7h-3l4 6H6l4-6H7l5-7Z" />
    <path stroke-linecap="round" d="M12 16v5" />
</svg>
@break
@case('golf')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path stroke-linecap="round" d="M7 21V3l10 4-10 4" />
    <path stroke-linecap="round" d="M4 21h8" />
</svg>
@break
@case('airport')
<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
    <path stroke-linejoin="round" d="M10 3l2 7 8 3-8 1-2 7-2-7-8-1 8-3 2-7Z" />
</svg>
@break
@endswitch