@props(['rating' => 5, 'size' => 'h-4 w-4', 'tone' => 'text-brass-500'])

{{-- Filled stars plus a screen-reader count, so the mark can be decorative. --}}
<span {{ $attributes->class('inline-flex items-center gap-0.5') }} role="img" aria-label="{{ $rating }} out of 5">
    @for ($i = 0; $i < 5; $i++)
        <svg @class([$size, $tone, 'opacity-25'=> $i >= $rating]) viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9 4.8 17.6l1-5.8L1.5 7.7l5.9-.9L10 1.5z" />
        </svg>
        @endfor
</span>