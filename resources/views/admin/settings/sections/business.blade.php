<form id="settings-form" method="POST" action="{{ route('admin.settings.update', 'business') }}" class="space-y-5">
    @csrf @method('PUT')

    <div class="grid gap-5 sm:grid-cols-2">
        <x-field label="Your name" name="name" required>
            <input id="name" name="name" class="input" value="{{ old('name', $agent['name']) }}" required>
        </x-field>
        <x-field label="Title" name="title" required hint="Shown under the logo and beside your photo.">
            <input id="title" name="title" class="input" value="{{ old('title', $agent['title']) }}" required>
        </x-field>
        <x-field label="Business name" name="agency" required>
            <input id="agency" name="agency" class="input" value="{{ old('agency', $agent['agency']) }}" required>
        </x-field>
        <x-field label="Registration / licence" name="license" hint="Left blank, the footer simply omits it.">
            <input id="license" name="license" class="input" value="{{ old('license', $agent['license']) }}">
        </x-field>
    </div>

    <x-field label="Tagline" name="tagline" hint="One line. Appears in the footer.">
        <input id="tagline" name="tagline" class="input" value="{{ old('tagline', $agent['tagline']) }}">
    </x-field>

    <x-field label="About you" name="bio" hint="Leave a blank line between paragraphs. Shown on the About page.">
        <textarea id="bio" name="bio" rows="9" class="input">{{ old('bio', implode("\n\n", $agent['bio'])) }}</textarea>
    </x-field>

    <fieldset class="rounded-lg border border-brass-200 bg-brass-50 p-5">
        <legend class="px-2 text-sm font-semibold text-brass-700">Headline figures</legend>
        <p class="mb-4 text-sm text-ink-600">
            These appear on the home page and About page as statements of fact about your business.
            Put your real numbers in.
        </p>
        <div class="grid gap-5 sm:grid-cols-3">
            <x-field label="Years in the trade" name="experience_years">
                <input id="experience_years" name="experience_years" type="number" min="0" class="input"
                       value="{{ old('experience_years', $agent['experience_years']) }}">
            </x-field>
            <x-field label="Properties sold" name="properties_sold">
                <input id="properties_sold" name="properties_sold" type="number" min="0" class="input"
                       value="{{ old('properties_sold', $agent['properties_sold']) }}">
            </x-field>
            <x-field label="Avg days to sell" name="avg_days_on_market">
                <input id="avg_days_on_market" name="avg_days_on_market" type="number" min="0" class="input"
                       value="{{ old('avg_days_on_market', $agent['avg_days_on_market']) }}">
            </x-field>
        </div>
    </fieldset>
</form>
