<form id="settings-form" method="POST" action="{{ route('admin.settings.update', 'contact') }}" class="space-y-5">
    @csrf @method('PUT')

    <div class="grid gap-5 sm:grid-cols-2">
        <x-field label="Phone (as displayed)" name="phone" required hint="How it reads on the page, spaces and all.">
            <input id="phone" name="phone" class="input" value="{{ old('phone', $agent['phone']) }}" required>
        </x-field>
        <x-field label="Phone (dial)" name="phone_dial" required hint="Digits only, with country code. Used by every call button.">
            <input id="phone_dial" name="phone_dial" class="input" value="{{ old('phone_dial', $agent['phone_dial']) }}" required>
        </x-field>
        <x-field label="Office phone" name="office_phone">
            <input id="office_phone" name="office_phone" class="input" value="{{ old('office_phone', $agent['office_phone']) }}">
        </x-field>
        <x-field label="Email" name="email" required>
            <input id="email" name="email" type="email" class="input" value="{{ old('email', $agent['email']) }}" required>
        </x-field>
    </div>

    <x-field label="WhatsApp number" name="whatsapp" hint="Digits only, no + and no spaces. e.g. 923227289296">
        <input id="whatsapp" name="whatsapp" class="input sm:max-w-xs" value="{{ old('whatsapp', $agent['whatsapp']) }}">
    </x-field>

    <div class="rounded-lg border border-ink-100 bg-sand-50 p-4">
        <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase">Preview</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="tel:{{ $agent['phone_dial'] }}" class="btn-primary !px-4 !py-2 text-xs">Call {{ $agent['phone'] }}</a>
            <a href="https://wa.me/{{ $agent['whatsapp'] }}" target="_blank" rel="noopener noreferrer"
               class="btn !px-4 !py-2 text-xs bg-[#25D366] text-ink-900">WhatsApp</a>
            <a href="mailto:{{ $agent['email'] }}" class="btn-outline !px-4 !py-2 text-xs">{{ $agent['email'] }}</a>
        </div>
    </div>
</form>
