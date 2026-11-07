@props(['eyebrow' => null, 'title', 'lead' => null, 'align' => 'left'])

<div @class([
    'max-w-2xl',
    'mx-auto text-center' => $align === 'center',
])>
    @if ($eyebrow)
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif
    <h2 class="mt-3 text-3xl sm:text-4xl">{{ $title }}</h2>
    @if ($lead)
        <p class="prose-page mt-4">{{ $lead }}</p>
    @endif
</div>
