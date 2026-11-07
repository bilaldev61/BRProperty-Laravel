@php
$tones = ['teal', 'brass', 'indigo', 'clay'];

// What the voice provider charges is agency spend rather than shop floor
// business, so the tile and the mention of cost in the intro go together.
$seesCost = auth()->user()?->can('finance.view') ?? false;

$tiles = [
['Calls', number_format($stats['calls']), 'Handled by the phone agent'],
['Leads', number_format($stats['leads']), 'Callers now in your inbox'],
['Minutes', number_format($stats['minutes']), 'Total talk time'],
];

if ($seesCost) {
$tiles[] = ['Call cost', '$'.number_format($stats['cost'] / 100, 2), 'As reported by the voice provider'];
}

$columns = count($tiles) === 4 ? 'sm:grid-cols-2 xl:grid-cols-4' : 'sm:grid-cols-3';
@endphp

<x-layouts.admin title="Calls" heading="Calls"
    :intro="$seesCost
        ? 'Every call the phone agent handled and what it cost. The figures cover the last 30 days.'
        : 'Every call the phone agent handled. The figures cover the last 30 days.'">

    <section aria-label="Last 30 days" class="grid gap-4 {{ $columns }}">
        @foreach ($tiles as $i => [$label, $value, $hint])
        <x-admin.stat :label="$label" :value="$value" :meta="$hint . ' · 30 days'" :tone="$tones[$i % 4]" />
        @endforeach
    </section>

    @if ($calls->isEmpty())
    <x-empty-state title="No calls yet" message="Calls handled by the phone agent appear here once n8n forwards them to this site." />
    @else
    <x-admin.card title="Recent calls" :padded="false" tone="indigo">
        <div class="divide-y divide-ink-50">
            @foreach ($calls as $call)
            <a href="{{ route('admin.calls.show', $call) }}"
                class="flex flex-col gap-2 px-5 py-4 transition hover:bg-sand-50 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2">
                        @if ($call->enquiry)
                        <x-priority-chip :priority="$call->enquiry->priority" class="!text-[11px]" />
                        @endif
                        <span class="truncate font-semibold text-ink-900">
                            {{ $call->enquiry?->name ?? ($call->from_number ?? 'Unknown caller') }}
                        </span>
                        <x-admin.badge>{{ $call->direction ?? 'call' }}</x-admin.badge>
                        @if ($call->in_voicemail)
                        <x-admin.badge>Voicemail</x-admin.badge>
                        @endif
                    </p>
                    <p class="mt-1 truncate text-sm text-ink-500">{{ $call->summary ?? 'No summary yet' }}</p>
                </div>
                <div class="shrink-0 text-right text-xs text-ink-400">
                    <span class="block tabular-nums">{{ $call->durationLabel() }}</span>
                    {{ $call->started_at?->setTimezone(config('agent.appointments.timezone'))->format('j M, g:ia') }}
                </div>
            </a>
            @endforeach
        </div>
    </x-admin.card>

    <div>{{ $calls->links() }}</div>
    @endif
</x-layouts.admin>