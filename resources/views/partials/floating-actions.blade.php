@php $agent = config('agent'); @endphp

{{-- Desktop: floating WhatsApp bubble --}}
<a href="https://wa.me/{{ $agent['whatsapp'] }}"
    target="_blank" rel="noopener noreferrer"
    class="fixed right-6 bottom-6 z-30 hidden h-14 w-14 items-center justify-center rounded-full bg-[#25D366] shadow-lg shadow-ink-900/20 transition hover:scale-105 xl:flex"
    aria-label="Message {{ $agent['agency'] }} on WhatsApp">
    <x-social-icon network="whatsapp" class="h-7 w-7 text-white" />
</a>

{{-- Mobile: sticky click-to-call bar, the single most important button on the site --}}
<div class="fixed inset-x-0 bottom-0 z-30 border-t border-ink-100 bg-white/95 backdrop-blur xl:hidden"
    style="padding-bottom: env(safe-area-inset-bottom)">
    <div class="grid grid-cols-3 gap-2 p-3">
        <a href="tel:{{ $agent['phone_dial'] }}"
            class="flex flex-col items-center justify-center gap-0.5 rounded-lg bg-ink-900 py-2.5 text-xs font-semibold text-sand-50"
            data-analytics="click-to-call-mobile">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.2.4 2.4.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1l-2.3 2.2Z" />
            </svg>
            Call
        </a>
        <a href="https://wa.me/{{ $agent['whatsapp'] }}" target="_blank" rel="noopener noreferrer"
            class="flex flex-col items-center justify-center gap-0.5 rounded-lg bg-[#25D366] py-2.5 text-xs font-semibold text-white">
            <x-social-icon network="whatsapp" class="h-5 w-5" />
            WhatsApp
        </a>
        <a href="{{ route('selling') }}#appraisal"
            class="flex flex-col items-center justify-center gap-0.5 rounded-lg bg-brass-500 py-2.5 text-xs font-semibold text-white">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10l8-6 8 6v10a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1Z" />
            </svg>
            Appraisal
        </a>
    </div>
</div>