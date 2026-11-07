@php
    $hours = collect($agent['hours'])->map(fn ($v, $k) => $k.' | '.$v)->implode("\n");
@endphp

<form id="settings-form" method="POST" action="{{ route('admin.settings.update', 'office') }}" class="space-y-5">
    @csrf @method('PUT')

    <div class="grid gap-5 sm:grid-cols-2">
        <x-field label="Street / office" name="street" required>
            <input id="street" name="street" class="input" value="{{ old('street', $agent['office']['street']) }}" required>
        </x-field>
        <x-field label="City" name="suburb" required>
            <input id="suburb" name="suburb" class="input" value="{{ old('suburb', $agent['office']['suburb']) }}" required>
        </x-field>
        <x-field label="Province / state" name="state" required>
            <input id="state" name="state" class="input" value="{{ old('state', $agent['office']['state']) }}" required>
        </x-field>
        <div class="grid grid-cols-2 gap-5">
            <x-field label="Postcode" name="postcode">
                <input id="postcode" name="postcode" class="input" value="{{ old('postcode', $agent['office']['postcode']) }}">
            </x-field>
            <x-field label="Country" name="country" required hint="Two letters, e.g. PK">
                <input id="country" name="country" maxlength="2" class="input uppercase" value="{{ old('country', $agent['office']['country']) }}" required>
            </x-field>
        </div>
        <x-field label="Latitude" name="lat" hint="Used by the map and structured data.">
            <input id="lat" name="lat" class="input" value="{{ old('lat', $agent['office']['lat']) }}">
        </x-field>
        <x-field label="Longitude" name="lng">
            <input id="lng" name="lng" class="input" value="{{ old('lng', $agent['office']['lng']) }}">
        </x-field>
    </div>

    <x-field label="Opening hours" name="hours" hint="One per line, as: Days | Hours — e.g. Monday - Thursday | 10:00am - 8:00pm">
        <textarea id="hours" name="hours" rows="5" class="input font-mono text-sm">{{ old('hours', $hours) }}</textarea>
    </x-field>

    <x-field label="Service areas" name="service_areas" hint="One per line. These become the suburb picker on the map and the footer list.">
        <textarea id="service_areas" name="service_areas" rows="8" class="input">{{ old('service_areas', implode("\n", $agent['service_areas'])) }}</textarea>
    </x-field>
</form>
