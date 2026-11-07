@php
// Colour is the section's identity, so it survives moving between them.
$tones = [
'business' => 'teal', 'contact' => 'brass', 'office' => 'indigo',
'social' => 'clay', 'branding' => 'teal', 'seo' => 'brass', 'format' => 'indigo',
];

$notes = [
'business' => 'Who you are, and the figures the marketing pages quote as fact.',
'contact' => 'Where enquiries reach you. Shown on the contact page and in the footer.',
'office' => 'The visiting address, opening hours and the areas you cover.',
'social' => 'Profile links in the footer. Blank rows are skipped.',
'branding' => 'Logo files and the accent colour used across the public site.',
'seo' => 'The titles and descriptions search engines read on every page.',
'format' => 'Currency, area units and price style wherever a listing is shown.',
];
@endphp

<x-layouts.admin title="Settings" heading="Site settings"
    intro="These override the defaults in config/agent.php and take effect across the whole site as soon as you save. Reset a section to fall back to the file.">
    <x-slot:actions>
        <a href="{{ route('home') }}" target="_blank" rel="noopener"
            class="text-sm font-medium text-ink-500 hover:text-ink-900">Preview site &rarr;</a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-[15rem_1fr] lg:items-start">

        {{-- Section nav --}}
        <x-admin.card :padded="false" class="lg:sticky lg:top-24">
            <nav aria-label="Settings sections" class="p-2">
                <ul class="flex gap-1 overflow-x-auto lg:block lg:space-y-0.5">
                    @foreach ($sections as $key => $label)
                    <li>
                        <a href="{{ route('admin.settings.index', ['section' => $key]) }}"
                            @class([ 'block rounded-lg px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors' , 'bg-ink-900 text-sand-50'=> $section === $key,
                            'text-ink-600 hover:bg-sand-100 hover:text-ink-900' => $section !== $key,
                            ])
                            @if ($section === $key) aria-current="page" @endif>{{ $label }}</a>
                    </li>
                    @endforeach
                </ul>
            </nav>
        </x-admin.card>

        <x-admin.card :padded="false" :tone="$tones[$section] ?? null"
            :title="$sections[$section]" :description="$notes[$section] ?? null">
            <div class="p-5 sm:p-6">
                @include('admin.settings.sections.'.$section)
            </div>

            <x-slot:footer>
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                    <p class="text-xs text-ink-400">Saved to the database, not to the file.</p>

                    <div class="flex flex-wrap items-center gap-4">
                        <form method="POST" action="{{ route('admin.settings.reset', $section) }}"
                            data-confirm="Your saved values for this section will be discarded and the file defaults restored."
                            data-confirm-title="Reset this section?"
                            data-confirm-button="Yes, reset it">
                            @csrf @method('DELETE')
                            <button type="submit"
                                class="text-sm font-medium text-ink-500 underline decoration-ink-300 underline-offset-2 hover:text-ink-900">
                                Reset to file defaults
                            </button>
                        </form>

                        <button type="submit" form="settings-form" class="btn-primary">Save changes</button>
                    </div>
                </div>
            </x-slot:footer>
        </x-admin.card>
    </div>
</x-layouts.admin>