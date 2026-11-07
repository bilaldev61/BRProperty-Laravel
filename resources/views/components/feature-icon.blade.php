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
@endswitch