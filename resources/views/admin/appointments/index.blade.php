<x-layouts.admin title="Appointments" heading="Appointments"
    intro="Viewings booked through the phone agent and the website. Confirm them here; the person is told either way.">

    <div class="flex flex-wrap items-center gap-2">
        @foreach (['' => 'Upcoming', 'past' => 'Past'] as $value => $label)
        <a href="{{ request()->fullUrlWithQuery(['view' => $value ?: null, 'page' => null]) }}"
            @class([ 'rounded-full border px-3.5 py-1.5 text-sm font-medium transition' , 'border-ink-900 bg-ink-900 text-sand-100'=> (string) request('view') === (string) $value,
            'border-ink-200 bg-white text-ink-600 hover:border-ink-400' => (string) request('view') !== (string) $value,
            ])>{{ $label }}</a>
        @endforeach

        @if ($toConfirm)
        <x-admin.badge tone="brass" class="!px-3 !py-1">
            {{ $toConfirm }} {{ Str::plural('viewing', $toConfirm) }} waiting for you to confirm
        </x-admin.badge>
        @endif
    </div>

    @if ($appointments->isEmpty())
    <x-empty-state :title="$past ? 'No past viewings' : 'No upcoming viewings'"
        message="Viewings booked by the phone agent appear here." />
    @else
    <x-admin.card :padded="false" tone="brass">
        <div class="divide-y divide-ink-50">
            @foreach ($appointments as $appointment)
            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-semibold text-ink-900">
                        {{ $appointment->whenLabel() }}
                        <x-admin.badge :tone="$appointment->status === \App\Models\Appointment::CONFIRMED ? 'green' : 'ink'">
                            {{ \App\Models\Appointment::statuses()[$appointment->status] ?? $appointment->status }}
                        </x-admin.badge>
                    </p>
                    <p class="mt-0.5 truncate text-sm text-ink-600">
                        <a href="{{ route('admin.enquiries.show', $appointment->enquiry) }}"
                            class="font-medium hover:underline">{{ $appointment->enquiry->name }}</a>
                        @if ($appointment->enquiry->phone) &middot; {{ $appointment->enquiry->phone }} @endif
                        @if ($appointment->property) &middot; {{ $appointment->property->title }} @endif
                    </p>
                    @if ($appointment->notes)
                    <p class="mt-0.5 truncate text-xs text-ink-400">{{ $appointment->notes }}</p>
                    @endif
                </div>

                @can('appointments.manage')
                <form method="POST" action="{{ route('admin.appointments.update', $appointment) }}"
                    class="flex shrink-0 items-center gap-2">
                    @csrf @method('PATCH')
                    <label class="sr-only" for="status-{{ $appointment->id }}">Status</label>
                    <select id="status-{{ $appointment->id }}" name="status" class="input !py-1.5 text-sm"
                        onchange="this.form.submit()">
                        @foreach (\App\Models\Appointment::statuses() as $value => $label)
                        <option value="{{ $value }}" @selected($appointment->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="btn-outline !py-1.5 text-sm">Save</button></noscript>
                </form>
                @endcan
            </div>
            @endforeach
        </div>
    </x-admin.card>

    <div>{{ $appointments->links() }}</div>
    @endif
</x-layouts.admin>