{{--
    Ghost rows shown while a Vue island's form is still hydrating.

    Sits inside the island's server-rendered fallback, so JavaScript visitors
    see an obvious "form loading" shape instead of an empty panel for the
    fraction of a second before the real fields mount. Visitors without
    JavaScript never see it: each island's <noscript> block hides this with a
    style rule before presenting the plain working form.
--}}
@props(['fields' => 4])

<div data-form-skeleton aria-hidden="true" class="mt-6 space-y-4">
    @for ($i = 0; $i < $fields; $i++)
        <div>
        <div class="mb-1.5 h-3.5 w-20 rounded bg-white/10 motion-safe:animate-pulse"></div>
        <div class="h-10 w-full rounded-lg bg-white/10 motion-safe:animate-pulse"></div>
</div>
@endfor
<div class="h-11 w-full rounded-lg bg-white/15 motion-safe:animate-pulse"></div>
</div>