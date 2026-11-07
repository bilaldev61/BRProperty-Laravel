@php
$tones = ['teal', 'brass', 'indigo', 'clay'];
@endphp

<x-layouts.admin title="Assistant chats" heading="Assistant chats"
    intro="What visitors asked the bubble, and how much of it the listings answered on their own. Figures cover the last 30 days.">
    <x-slot:actions>
        <a href="{{ route('admin.assistant.questions') }}" class="btn-outline !px-4 !py-2 text-xs">Unanswered questions</a>
    </x-slot:actions>

    <section aria-label="Last 30 days" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
        ['Chats', $stats['chats'], 'Visitors who asked at least one question'],
        ['Leads', $stats['leads'], 'Chats that left a name and number'],
        ['Conversion', $stats['conversion'].'%', 'Chats that became a lead'],
        ['Unanswered', $stats['questions'], 'Questions the listings could not answer'],
        ['Answered without AI', $stats['without_ai'].'%', 'Replies built straight from your listing data - free'],
        ['AI replies', number_format($stats['ai_replies']), 'Replies that needed the model'],
        ['AI tokens used', number_format($stats['tokens']), number_format($stats['per_reply']).' per AI reply on average'],
        ] as $i => [$label, $value, $hint])
        <x-admin.stat :label="$label" :value="$value" :meta="$hint . ' · 30 days'" :tone="$tones[$i % 4]" />
        @endforeach
    </section>

    <div class="flex flex-wrap gap-2">
        @foreach (['' => 'All chats', '1' => 'Became a lead'] as $value => $label)
        <a href="{{ request()->fullUrlWithQuery(['leads' => $value ?: null, 'page' => null]) }}"
            @class([ 'rounded-full border px-3.5 py-1.5 text-sm font-medium transition' , 'border-ink-900 bg-ink-900 text-sand-100'=> (string) request('leads') === (string) $value,
            'border-ink-200 bg-white text-ink-600 hover:border-ink-400' => (string) request('leads') !== (string) $value,
            ])>{{ $label }}</a>
        @endforeach
    </div>

    @if ($conversations->isEmpty())
    <x-empty-state title="No chats yet" message="Conversations from the assistant on the public site appear here." />
    @else
    <x-admin.card :padded="false" tone="plum">
        <div class="divide-y divide-ink-50">
            @foreach ($conversations as $conversation)
            <a href="{{ route('admin.assistant.show', $conversation) }}"
                class="flex flex-col gap-2 px-5 py-4 transition hover:bg-sand-50 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2">
                        <span class="truncate font-semibold text-ink-900">
                            {{ $conversation->enquiry?->name ?? 'Anonymous visitor' }}
                        </span>
                        @if ($conversation->isLead())
                        <x-admin.badge tone="green">Lead</x-admin.badge>
                        @endif
                        @if ($conversation->unanswered_questions_count)
                        <x-admin.badge tone="brass">
                            {{ $conversation->unanswered_questions_count }} unanswered
                        </x-admin.badge>
                        @endif
                    </p>
                    <p class="mt-1 truncate text-sm text-ink-500">
                        {{ $conversation->property ? 'On '.$conversation->property->title : 'General site chat' }}
                        &middot; {{ $conversation->visitor_messages }} {{ Str::plural('message', $conversation->visitor_messages) }}
                    </p>
                </div>
                <span class="shrink-0 text-xs text-ink-400">{{ $conversation->last_message_at?->format('j M Y, g:ia') }}</span>
            </a>
            @endforeach
        </div>
    </x-admin.card>

    <div>{{ $conversations->links() }}</div>
    @endif
</x-layouts.admin>