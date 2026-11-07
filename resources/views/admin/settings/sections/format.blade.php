@php use App\Support\Format; @endphp

<form id="settings-form" method="POST" action="{{ route('admin.settings.update', 'format') }}" class="space-y-5">
    @csrf @method('PUT')

    <div class="grid gap-5 sm:grid-cols-3">
        <x-field label="Currency code" name="currency" required>
            <input id="currency" name="currency" maxlength="8" class="input uppercase"
                value="{{ old('currency', $agent['format']['price']['currency']) }}" required>
        </x-field>

        <x-field label="Price style" name="style" required>
            <select id="style" name="style" class="input">
                <option value="subcontinent" @selected(old('style', $agent['format']['price']['style'])==='subcontinent' )>Crore / Lakh</option>
                <option value="western" @selected(old('style', $agent['format']['price']['style'])==='western' )>Thousands separated</option>
            </select>
        </x-field>

        <x-field label="Land unit" name="unit" required>
            <select id="unit" name="unit" class="input">
                <option value="sqft" @selected(old('unit', $agent['format']['area']['unit'])==='sqft' )>Square feet</option>
                <option value="sqm" @selected(old('unit', $agent['format']['area']['unit'])==='sqm' )>Square metres</option>
                <option value="marla" @selected(old('unit', $agent['format']['area']['unit'])==='marla' )>Marla / Kanal</option>
            </select>
        </x-field>
    </div>

    <div class="rounded-lg border border-ink-100 bg-sand-50 p-4">
        <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase">How it reads right now</p>
        <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            @foreach ([1285000, 625000, 45000] as $amount)
            <div class="flex justify-between gap-4 border-b border-ink-100 pb-1.5">
                <dt class="text-ink-500 tabular-nums">{{ number_format($amount) }}</dt>
                <dd class="font-semibold text-ink-900">{{ Format::price($amount) }}</dd>
            </div>
            @endforeach
            @foreach ([1800, 2450, 7200] as $area)
            <div class="flex justify-between gap-4 border-b border-ink-100 pb-1.5">
                <dt class="text-ink-500 tabular-nums">{{ $area }} {{ Format::areaUnitLabel() }}</dt>
                <dd class="font-semibold text-ink-900">{{ Format::area($area) }}</dd>
            </div>
            @endforeach
        </dl>
        <p class="mt-3 text-xs text-ink-400">Save to update this preview.</p>
    </div>

    <div class="rounded-lg border-l-4 border-brass-500 bg-brass-50 p-4 text-sm text-ink-700">
        <strong class="font-semibold">Changing the land unit does not convert existing listings.</strong>
        Land size is stored as a plain number; switching the unit only changes how it is labelled.
        Re-enter the figures on your listings if you switch.
    </div>
</form>