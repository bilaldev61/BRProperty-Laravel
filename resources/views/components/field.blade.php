@props(['label', 'name', 'hint' => null, 'required' => false])

<div>
    <label class="label" for="{{ $name }}">
        {{ $label }}
        @unless ($required)<span class="font-normal text-ink-400">(optional)</span>@endunless
    </label>
    {{ $slot }}
    @if ($hint)
        <p class="mt-1 text-xs text-ink-400">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
