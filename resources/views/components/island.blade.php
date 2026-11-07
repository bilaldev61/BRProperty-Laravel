{{--
    Mounts a Vue island over server-rendered fallback markup.

    The props are JSON-encoded into a data attribute rather than passed through
    Blade's @json directive, which cannot parse multi-line array expressions.
--}}
@props(['name', 'props' => []])

<div
    data-vue="{{ $name }}"
    data-props="{{ json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
    {{ $attributes }}
>
    {{ $slot }}
</div>
