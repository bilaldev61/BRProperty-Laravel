<form id="settings-form" method="POST" action="{{ route('admin.settings.update', 'seo') }}" class="space-y-5">
    @csrf @method('PUT')

    <x-field label="Site name" name="site_name" required hint="Appended to every page title.">
        <input id="site_name" name="site_name" class="input" value="{{ old('site_name', $agent['seo']['site_name']) }}" required>
    </x-field>

    <x-field label="Default description" name="description" required hint="Used on pages without their own. Aim for 150–160 characters.">
        <textarea id="description" name="description" rows="3" class="input" maxlength="300" required>{{ old('description', $agent['seo']['description']) }}</textarea>
    </x-field>

    <x-field label="Keywords" name="keywords" hint="Comma separated. Google ignores these, but some other engines still read them.">
        <input id="keywords" name="keywords" class="input" value="{{ old('keywords', $agent['seo']['keywords']) }}">
    </x-field>

    <div class="grid gap-5 sm:grid-cols-2">
        <x-field label="Locale" name="locale" required hint="e.g. en_PK">
            <input id="locale" name="locale" class="input" value="{{ old('locale', $agent['seo']['locale']) }}" required>
        </x-field>
        <x-field label="X / Twitter handle" name="twitter">
            <input id="twitter" name="twitter" class="input" value="{{ old('twitter', $agent['seo']['twitter']) }}" placeholder="@yourhandle">
        </x-field>
    </div>

    <div class="rounded-lg border border-ink-100 bg-sand-50 p-4">
        <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Search result preview</p>
        <div class="mt-3">
            <p class="text-xs text-teal-700">{{ url('/') }}</p>
            <p class="mt-0.5 text-lg text-indigo-700">{{ $agent['seo']['site_name'] }}</p>
            <p class="mt-0.5 text-sm text-ink-600">{{ Str::limit($agent['seo']['description'], 160) }}</p>
        </div>
    </div>

    <p class="text-sm text-ink-500">
        <a href="{{ route('sitemap') }}" target="_blank" class="font-medium text-brass-600 hover:underline">View sitemap.xml</a>
        &middot;
        <a href="{{ route('robots') }}" target="_blank" class="font-medium text-brass-600 hover:underline">View robots.txt</a>
    </p>
</form>
