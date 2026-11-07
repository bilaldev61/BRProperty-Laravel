@props(['title', 'message' => null, 'actionUrl' => null, 'actionLabel' => null])

<div class="rounded-card border border-dashed border-ink-200 bg-white px-6 py-16 text-center">
    <svg class="mx-auto h-12 w-12 text-ink-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10l8-6 8 6v10a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1Z"/>
    </svg>
    <h3 class="mt-4 font-sans text-lg font-semibold">{{ $title }}</h3>
    @if ($message)
        <p class="mx-auto mt-2 max-w-sm text-sm text-ink-500">{{ $message }}</p>
    @endif
    @if ($actionUrl)
        <a href="{{ $actionUrl }}" class="btn-outline mt-6">{{ $actionLabel ?? 'Back' }}</a>
    @endif
</div>
