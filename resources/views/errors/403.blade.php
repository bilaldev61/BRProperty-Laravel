@php
// Reached when a signed-in account tries a section its role does not include.
// The menu already hides those links, so this is either a stale tab or a
// typed URL - either way, say which and offer the way back.
$role = auth()->user()?->role;
@endphp

<x-layouts.admin title="Not allowed" heading="Not allowed">
    <x-admin.card class="max-w-2xl" tone="clay">
        <p class="font-display text-xl text-ink-900">Your role does not include this section.</p>

        <p class="mt-3 text-sm leading-relaxed text-ink-600">
            @if ($role)
            You are signed in as <strong class="font-semibold text-ink-900">{{ $role->label() }}</strong>, which can
            {{ collect($role->abilities())->map(fn ($ability) => \App\Enums\Role::ABILITIES[$ability] ?? $ability)->take(3)->map(fn ($label) => \Illuminate\Support\Str::lcfirst($label))->implode(', ') }}
            and more, but not this page.
            @else
            This page is not part of your account's permissions.
            @endif
            If you need it, ask an owner to change your role.
        </p>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('admin.dashboard') }}" class="btn-primary">Back to the dashboard</a>
            <a href="{{ route('admin.profile.edit') }}" class="btn-outline">Your account</a>
        </div>
    </x-admin.card>
</x-layouts.admin>