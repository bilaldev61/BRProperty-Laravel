<form id="settings-form" method="POST" action="{{ route('admin.settings.update', 'social') }}" class="space-y-5">
    @csrf @method('PUT')

    <p class="text-sm text-ink-500">Leave a field empty to hide that icon from the footer and contact page.</p>

    @foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube'] as $key => $label)
        <x-field :label="$label" :name="$key">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ink-900 text-sand-50">
                    <x-social-icon :network="$key" class="h-4 w-4" />
                </span>
                <input id="{{ $key }}" name="{{ $key }}" type="url" class="input"
                       value="{{ old($key, $agent['social'][$key] ?? '') }}" placeholder="https://">
            </div>
        </x-field>
    @endforeach
</form>
