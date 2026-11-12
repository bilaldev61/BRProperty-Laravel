@php
$tones = ['teal', 'brass', 'indigo', 'clay'];

$wait = $stats['latency'] >= 1000
? number_format($stats['latency'] / 1000, 1).'s'
: $stats['latency'].'ms';
@endphp

<x-layouts.admin title="Model usage" heading="Model usage"
    intro="Every request the assistant sent to the model, and what came back. A reply that calls a tool costs more than one request, so the rows here outnumber the AI replies in the chat list. Figures cover the last 30 days.">
    <x-slot:actions>
        <a href="{{ route('admin.assistant.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-900">&larr;
            All chats</a>
    </x-slot:actions>

    @if ($stats['requests'] === 0)
    <x-empty-state title="No model requests yet"
        message="Every reply the model writes records a row here. Answers built from your listing data are free and record nothing." />
    @else
    <section aria-label="Last 30 days" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ([
        ['Model requests', number_format($stats['requests']), 'One per request, not per reply'],
        ['Prompt tokens', number_format($stats['prompt']), 'Sent to the model, re-sent every round'],
        ['Reply tokens', number_format($stats['completion']), 'What the model wrote back'],
        ['Reused from cache', number_format($stats['cached']), $stats['cache_rate'].'% of the prompt · '.number_format($stats['written']).' written to cache'],
        ['Average wait', $wait, 'Per request, tool rounds included'],
        ['Extra tool rounds', number_format($stats['tool_rounds']), 'Requests a tool call added to a reply'],
        ] as $i => [$label, $value, $hint])
        <x-admin.stat :label="$label" :value="$value" :meta="$hint . ' · 30 days'" :tone="$tones[$i % 4]" />
        @endforeach
    </section>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.1fr] lg:items-start">
        <x-admin.card title="By model" tone="indigo"
            description="Which model answered, and whether it reused anything.">
            <ul class="space-y-4">
                @foreach ($byModel as $row)
                <li>
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="truncate font-mono text-xs text-ink-900">{{ $row->model }}</span>
                        <span class="shrink-0 text-sm font-semibold text-ink-700 tabular-nums">
                            {{ number_format($row->requests) }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-ink-500 tabular-nums">
                        {{ number_format($row->prompt) }} prompt tokens
                        &middot; {{ $row->prompt ? round($row->cached / $row->prompt * 100) : 0 }}% reused
                        &middot; {{ number_format($row->latency) }}ms average
                    </p>
                </li>
                @endforeach
            </ul>
        </x-admin.card>

        <x-admin.card title="Reading these numbers" tone="brass">
            <div class="space-y-3 text-sm text-ink-600">
                <p>
                    <strong class="font-semibold text-ink-900">Prompt</strong> is what we send: the instructions,
                    the tool menu and the recent conversation. It goes out in full on every request, which is why
                    it dwarfs the reply.
                </p>
                <p>
                    <strong class="font-semibold text-ink-900">Reused from cache</strong> is the part the provider
                    recognised from an earlier request and charged less for. Whether that happens is the
                    provider's decision, not a setting on this site.
                </p>
                <p>
                    A free model reports zero here every time. That is expected rather than a fault, and it costs
                    nothing, because the free tier is already free. The number starts saving money on a paid model.
                </p>
                <p class="text-ink-500">
                    Answers built from your listing data never reach the model and record no row at all - the
                    &ldquo;Answered without AI&rdquo; figure on the chat list is the one that goes up when the
                    rules absorb more.
                </p>
            </div>
        </x-admin.card>
    </div>

    <x-admin.card title="Recent requests" :padded="false" tone="teal"
        description="Newest first. Each row links to the chat it came from.">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[64rem] text-left text-sm">
                <thead class="border-b border-ink-100 bg-sand-50 text-[11px] tracking-[0.12em] text-ink-500 uppercase">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Request</th>
                        <th class="px-5 py-3 font-semibold">Chat</th>
                        <th class="px-5 py-3 font-semibold">Round</th>
                        <th class="px-5 py-3 font-semibold">Prompt</th>
                        <th class="px-5 py-3 font-semibold">Reply</th>
                        <th class="px-5 py-3 font-semibold">Reused</th>
                        <th class="px-5 py-3 font-semibold">Wait</th>
                        <th class="px-5 py-3 font-semibold">Tools called</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-50">
                    @foreach ($requests as $row)
                    <tr class="hover:bg-sand-50">
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="block text-ink-700">{{ $row->created_at->format('j M, g:ia') }}</span>
                            <span class="block font-mono text-[11px] text-ink-400">{{ $row->model }}</span>
                        </td>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.assistant.show', $row->chat_conversation_id) }}"
                                class="font-medium text-ink-900 hover:underline">
                                {{ $row->conversation?->enquiry?->name ?? 'Anonymous visitor' }}
                            </a>
                            <span class="block max-w-[16rem] truncate text-xs text-ink-500">
                                {{ Str::limit($row->message?->content, 48) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            @if ($row->round === 0)
                            <span class="text-ink-500">first</span>
                            @else
                            <x-admin.badge tone="brass">tool {{ $row->round }}</x-admin.badge>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-ink-700 tabular-nums">{{ number_format($row->prompt_tokens) }}</td>
                        <td class="px-5 py-3 text-ink-700 tabular-nums">{{ number_format($row->completion_tokens) }}</td>
                        <td @class(['px-5 py-3 tabular-nums', 'text-emerald-700 font-semibold'=> $row->cached_tokens > 0, 'text-ink-400' => $row->cached_tokens === 0])>
                            {{ number_format($row->cached_tokens) }}
                        </td>
                        <td class="px-5 py-3 text-ink-700 tabular-nums whitespace-nowrap">{{ $row->latencyLabel() }}</td>
                        <td class="px-5 py-3">
                            @if ($row->toolNames() === [])
                            <span class="text-ink-400">&mdash;</span>
                            @else
                            <span class="flex flex-wrap gap-1">
                                @foreach ($row->toolNames() as $tool)
                                <x-admin.badge tone="teal">{{ $tool }}</x-admin.badge>
                                @endforeach
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div>{{ $requests->links() }}</div>
    @endif
</x-layouts.admin>