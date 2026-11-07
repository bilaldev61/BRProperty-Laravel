<x-layouts.admin title="Assistant chat" heading="Assistant chat"
    :intro="'Started '.$conversation->created_at->format('l j F Y, g:ia').' · '.$conversation->visitor_messages.' '.Str::plural('message', $conversation->visitor_messages).' from the visitor.'">
    <x-slot:actions>
        <a href="{{ route('admin.assistant.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-900">&larr;
            All chats</a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr] lg:items-start">
        <x-admin.card :padded="false" tone="plum" title="Transcript"
            description="What the visitor asked, and what they were told.">
            <div class="p-5 sm:p-6">
                @include('admin.assistant._transcript', ['messages' => $conversation->messages])
            </div>
        </x-admin.card>

        <aside class="space-y-4">
            <x-admin.card title="Outcome" tone="brass">
                @if ($conversation->enquiry)
                <p class="text-sm text-ink-600">
                    <x-admin.badge tone="green" class="mb-2">Became a lead</x-admin.badge>
                    <span class="block">{{ $conversation->enquiry->name }} left their details.</span>
                </p>
                <a href="{{ route('admin.enquiries.show', $conversation->enquiry) }}"
                    class="btn-primary mt-4 w-full">Open the enquiry</a>
                @else
                <p class="text-sm text-ink-500">
                    No contact details were left. Anonymous chats are deleted after
                    {{ config('ai.chat.retention_days') }} days.
                </p>
                @endif
            </x-admin.card>

            @if ($conversation->property)
            <x-admin.card title="Started on" tone="teal">
                <div class="flex items-center gap-4">
                    <img src="{{ $conversation->property->heroImageUrl() }}" alt="" loading="lazy" decoding="async"
                        class="h-14 w-20 shrink-0 rounded-md object-cover">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-ink-900">{{ $conversation->property->title }}</p>
                        @can('properties.manage')
                        <a href="{{ route('admin.properties.edit', $conversation->property) }}"
                            class="text-sm font-medium text-brass-600 hover:underline">Edit listing</a>
                        @endcan
                    </div>
                </div>
            </x-admin.card>
            @endif

            @if ($conversation->unansweredQuestions->isNotEmpty())
            <x-admin.card title="Could not answer" tone="ochre">
                <ul class="space-y-2 text-sm">
                    @foreach ($conversation->unansweredQuestions as $question)
                    <li>
                        <x-admin.badge tone="brass">{{ $question->topicLabel() }}</x-admin.badge>
                        <span class="mt-1 block text-ink-700">{{ $question->question }}</span>
                    </li>
                    @endforeach
                </ul>
            </x-admin.card>
            @endif
        </aside>
    </div>
</x-layouts.admin>