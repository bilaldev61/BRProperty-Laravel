<x-layouts.admin title="Profile" heading="Your profile"
    intro="The account you sign in with, and the portrait the website uses for you. Your public contact details live in settings instead.">
    <x-slot:actions>
        @can('settings.manage')
        <a href="{{ route('admin.settings.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-900">Site
            settings &rarr;</a>
        @endcan
    </x-slot:actions>

    {{-- The three numbers that describe the account, before the forms. --}}
    <section aria-label="At a glance" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Listings managed" :value="number_format($stats['listings'])" meta="On the site, drafts included"
            tone="teal" />
        <x-admin.stat label="Transfers completed" :value="number_format($stats['sold'])" meta="And counting" tone="brass" />
        <x-admin.stat label="Enquiries received" :value="number_format($stats['enquiries'])" meta="Across every channel"
            tone="indigo" />
        <x-admin.stat label="Account created" :value="$stats['joined']?->format('M Y') ?? '—'"
            :meta="$stats['joined']?->format('j F Y') ?? 'Unknown'" tone="clay" />
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,20rem)_minmax(0,1fr)] xl:items-start">

        {{-- Portrait + who the account belongs to --}}
        <x-admin.card :padded="false" tone="teal" title="Portrait">
            @php $portrait = \App\Support\Media::agent('avatar') ?? \App\Support\Media::agent('photo'); @endphp

            <div class="p-6 text-center">
                @if ($portrait)
                <img src="{{ $portrait }}" alt="{{ $user->name }}"
                    class="mx-auto h-28 w-28 rounded-full object-cover ring-4 ring-teal-100" width="256" height="256">
                @else
                <div
                    class="mx-auto flex h-28 w-28 items-center justify-center rounded-full bg-ink-100 font-display text-3xl text-ink-500">
                    {{ Str::of($user->name)->explode(' ')->take(2)->map(fn ($p) => Str::substr($p, 0, 1))->implode('') }}
                </div>
                @endif

                <p class="mt-4 flex flex-wrap items-center justify-center gap-2">
                    <span class="font-display text-xl text-ink-900">{{ $user->name }}</span>
                    @if ($user->role)
                    <x-admin.badge :tone="$user->role->tone()">{{ $user->role->label() }}</x-admin.badge>
                    @endif
                </p>
                <p class="mt-0.5 text-sm text-ink-500">{{ config('agent.title') }}, {{ config('agent.agency') }}</p>

                <form method="POST" action="{{ route('admin.profile.photo') }}" enctype="multipart/form-data"
                    class="mt-6 text-left">
                    @csrf
                    <label class="label" for="photo">Replace portrait</label>
                    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required
                        class="w-full rounded-lg border border-dashed border-ink-300 bg-sand-50 px-3 py-3 text-sm file:mr-3 file:rounded-full file:border-0 file:bg-ink-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-sand-50">
                    <p class="mt-1 text-xs text-ink-400">Square works best. Used on the home page, About page and every
                        listing.</p>
                    @error('photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="btn-primary mt-3 w-full">Upload portrait</button>
                </form>
            </div>
        </x-admin.card>

        {{-- Account details and password --}}
        <div class="space-y-6">
            <x-admin.card tone="brass" title="Account details"
                description="This is the account you sign in with. It is separate from the public contact details, which live in settings.">
                <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-5">
                    @csrf @method('PUT')

                    <x-field label="Name" name="name" required>
                        <input id="name" name="name" class="input" value="{{ old('name', $user->name) }}" required>
                    </x-field>

                    <x-field label="Sign-in email" name="email" required>
                        <input id="email" name="email" type="email" class="input" value="{{ old('email', $user->email) }}"
                            required>
                    </x-field>

                    <button type="submit" class="btn-primary">Save details</button>
                </form>
            </x-admin.card>

            <x-admin.card tone="clay" title="Change password"
                description="Changing your password signs out every other device.">
                <form method="POST" action="{{ route('admin.profile.password') }}" class="space-y-5">
                    @csrf @method('PUT')

                    <x-field label="Current password" name="current_password" required>
                        <input id="current_password" name="current_password" type="password"
                            autocomplete="current-password" class="input sm:max-w-sm" required>
                    </x-field>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field label="New password" name="password" required hint="At least 8 characters.">
                            <input id="password" name="password" type="password" autocomplete="new-password" class="input"
                                required>
                        </x-field>
                        <x-field label="Confirm new password" name="password_confirmation" required>
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                autocomplete="new-password" class="input" required>
                        </x-field>
                    </div>

                    <button type="submit" class="btn-primary">Change password</button>
                </form>
            </x-admin.card>
        </div>
    </div>
</x-layouts.admin>