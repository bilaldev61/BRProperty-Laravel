<form id="settings-form" method="POST" action="{{ route('admin.settings.update', 'branding') }}"
      enctype="multipart/form-data" class="space-y-6">
    @csrf @method('PUT')

    <p class="text-sm text-ink-500">
        SVG keeps the logo sharp at every size. PNG and WebP work too. Leave a field empty to keep the current file.
        Your portrait is on the <a href="{{ route('admin.profile.edit') }}" class="font-medium text-brass-600 hover:underline">profile page</a>.
    </p>

    @foreach ([
        ['logo_mark',  'Square mark',      'Header, admin sidebar and the login screen.',  'bg-sand-100'],
        ['logo',       'Horizontal lockup','For light backgrounds. Not used on the site by default.', 'bg-sand-100'],
        ['logo_light', 'Reversed lockup',  'Footer and login screen, on dark.',            'bg-ink-900'],
    ] as [$key, $label, $hint, $swatch])
        <div class="grid gap-4 rounded-lg border border-ink-100 p-4 sm:grid-cols-[11rem_1fr] sm:items-center">
            <div class="flex h-24 items-center justify-center rounded-lg {{ $swatch }} p-4">
                @if (\App\Support\Media::agent($key))
                    <img src="{{ \App\Support\Media::agent($key) }}" alt="{{ $label }}" class="max-h-16 max-w-full">
                @else
                    <span class="text-xs text-ink-400">none set</span>
                @endif
            </div>
            <x-field :label="$label" :name="$key" :hint="$hint">
                <input id="{{ $key }}" name="{{ $key }}" type="file" accept=".svg,image/png,image/jpeg,image/webp"
                       class="w-full rounded-lg border border-dashed border-ink-300 bg-sand-50 px-4 py-3 text-sm file:mr-4 file:rounded-full file:border-0 file:bg-ink-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-sand-50">
            </x-field>
        </div>
    @endforeach

    <p class="text-xs text-ink-400">Max 1&nbsp;MB per file. Uploading replaces the previous file and deletes it from storage.</p>
</form>
