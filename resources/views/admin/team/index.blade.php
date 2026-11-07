@php
$initials = fn ($user) => \Illuminate\Support\Str::of($user->name)->explode(' ')->take(2)->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))->implode('');
@endphp

<x-layouts.admin title="Team" heading="Team"
    intro="Everyone who can sign in, and what each role is allowed to do. Only owners can see this page.">

    <div class="grid gap-6 xl:grid-cols-[1.4fr_1fr] xl:items-start">

        <x-admin.card title="Accounts" :description="$members->count() . ' ' . \Illuminate\Support\Str::plural('person', $members->count()) . ' with access.'" :padded="false" tone="brass">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-left text-sm">
                    <thead class="border-b border-ink-100 bg-sand-50 text-[11px] tracking-[0.12em] text-ink-500 uppercase">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Person</th>
                            <th class="px-5 py-3 font-semibold">Role</th>
                            <th class="px-5 py-3 font-semibold">Added</th>
                            <th class="px-5 py-3"><span class="sr-only">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-50">
                        @foreach ($members as $member)
                        <tr class="align-top hover:bg-sand-50/60">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink-100 text-xs font-semibold text-ink-700"
                                        aria-hidden="true">{{ $initials($member) }}</span>
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-2 font-medium text-ink-900">
                                            <span class="truncate">{{ $member->name }}</span>
                                            @if ($me && $member->is($me))
                                            <x-admin.badge>You</x-admin.badge>
                                            @endif
                                        </p>
                                        <p class="truncate text-xs text-ink-500">{{ $member->email }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <form method="POST" action="{{ route('admin.team.update', $member) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    {{-- Role is the only editable field here; name is carried along so the same validation applies. --}}
                                    <input type="hidden" name="name" value="{{ $member->name }}">
                                    <label class="sr-only" for="role-{{ $member->id }}">Role for {{ $member->name }}</label>
                                    <select id="role-{{ $member->id }}" name="role" class="input !w-auto !py-1.5 !text-xs">
                                        @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected($member->role === $role)>{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="text-xs font-semibold text-brass-700 hover:underline">Save</button>
                                </form>
                                <p class="mt-1.5 max-w-xs text-xs text-ink-400">{{ $member->role?->summary() }}</p>
                            </td>

                            <td class="px-5 py-4 text-xs whitespace-nowrap text-ink-500">
                                {{ $member->created_at?->format('j M Y') ?? '—' }}
                            </td>

                            <td class="px-5 py-4 text-right">
                                @if (! $me || ! $member->is($me))
                                <form method="POST" action="{{ route('admin.team.destroy', $member) }}"
                                    data-confirm="{{ $member->name }} will no longer be able to sign in. Their name stays on anything they already wrote."
                                    data-confirm-title="Remove {{ $member->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline">Remove</button>
                                </form>
                                @else
                                <span class="text-xs text-ink-300">Signed in</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.card>

        <x-admin.card title="Add someone" description="They can sign in as soon as you save. Send them the password yourself." tone="teal">
            <form method="POST" action="{{ route('admin.team.store') }}" class="space-y-4">
                @csrf

                <x-field label="Name" name="name" required>
                    <input id="name" name="name" class="input" value="{{ old('name') }}" required autocomplete="off">
                </x-field>

                <x-field label="Sign-in email" name="email" required>
                    <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required autocomplete="off">
                </x-field>

                <x-field label="Role" name="role" required>
                    <select id="role" name="role" class="input">
                        @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role')===$role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </x-field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Password" name="password" required hint="At least 10 characters.">
                        <input id="password" name="password" type="password" class="input" required autocomplete="new-password">
                    </x-field>

                    <x-field label="Repeat password" name="password_confirmation" required>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="input" required
                            autocomplete="new-password">
                    </x-field>
                </div>

                <button type="submit" class="btn-primary w-full">Give them access</button>
            </form>
        </x-admin.card>
    </div>

    <x-admin.card title="What each role can do" description="Change a role above and the menu they see changes on their next page load.">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead>
                    <tr class="border-b border-ink-100 text-[11px] tracking-[0.12em] text-ink-500 uppercase">
                        <th class="py-2 pr-4 font-semibold">Ability</th>
                        @foreach ($roles as $role)
                        <th class="px-3 py-2 text-center font-semibold" title="{{ $role->summary() }}">{{ $role->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-50">
                    @foreach ($abilities as $ability => $label)
                    <tr>
                        <td class="py-2.5 pr-4 text-ink-700">{{ $label }}</td>
                        @foreach ($roles as $role)
                        <td class="px-3 py-2.5 text-center">
                            @if ($role->can($ability))
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                                <svg class="h-3 w-3" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2.2"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.5 6.5 2.5 2.5 4.5-5" />
                                </svg>
                                <span class="sr-only">Allowed</span>
                            </span>
                            @else
                            <span class="text-ink-300" aria-hidden="true">&mdash;</span>
                            <span class="sr-only">Not allowed</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>
</x-layouts.admin>