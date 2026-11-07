<x-layouts.admin title="Unanswered questions" heading="Unanswered questions"
    :intro="'Questions visitors asked the assistant in the last '.$days.' days that your listings could not answer, so it deferred them to you. The topics that come up most are the facts most worth adding to your listings.'">
    <x-slot:actions>
        <a href="{{ route('admin.assistant.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-900">&larr;
            All chats</a>
    </x-slot:actions>

    @if ($total === 0)
    <x-empty-state title="Nothing unanswered"
        message="When the assistant has to defer a question to you, it shows up here." />
    @else
    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr] lg:items-start">
        <x-admin.card :padded="false" tone="plum" title="By topic"
            description="{{ $total }} deferred to you, grouped by what was asked.">
            @php
            // The bar is drawn in twelfths rather than a percentage: it reads the
            // same to the eye and keeps Blade out of a CSS value, where an
            // editor's CSS parser reports the interpolation as broken.
            $steps = ['w-0', 'w-1/12', 'w-1/6', 'w-1/4', 'w-1/3', 'w-5/12', 'w-1/2',
            'w-7/12', 'w-2/3', 'w-3/4', 'w-5/6', 'w-11/12', 'w-full'];
            $max = max(1, (int) $byTopic->max('count'));
            @endphp
            <div class="divide-y divide-ink-50">
                @foreach ($byTopic as $row)
                @php $step = $steps[max(1, (int) round($row['count'] / $max * 12))]; @endphp
                <div class="px-5 py-4">
                    <div class="flex items-baseline justify-between gap-4">
                        <h3 class="font-sans text-sm font-semibold text-ink-900">{{ $row['label'] }}</h3>
                        <span class="text-sm font-semibold text-ink-700 tabular-nums">{{ $row['count'] }}</span>
                    </div>
                    <div class="mt-2 h-1.5 rounded-full bg-ink-50" aria-hidden="true">
                        <div class="h-1.5 rounded-full bg-plum-500 {{ $step }}"></div>
                    </div>
                    <ul class="mt-3 space-y-1.5 text-sm text-ink-600">
                        @foreach ($row['examples'] as $example)
                        <li>
                            &ldquo;{{ $example->question }}&rdquo;
                            @if ($example->property)
                            <span class="text-ink-400">&middot; {{ $example->property->title }}</span>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </div>
        </x-admin.card>

        <x-admin.card title="Listings with the most gaps" tone="brass">
            @if ($byProperty->isEmpty())
            <p class="text-sm text-ink-500">None of these questions were about a specific listing.</p>
            @else
            <ul class="space-y-4">
                @foreach ($byProperty as $row)
                <li>
                    <div class="flex items-baseline justify-between gap-3">
                        @can('properties.manage')
                        <a href="{{ route('admin.properties.edit', $row['property']) }}"
                            class="truncate font-medium text-ink-900 hover:underline">{{ $row['property']->title }}</a>
                        @else
                        <span class="truncate font-medium text-ink-900">{{ $row['property']->title }}</span>
                        @endcan
                        <span class="shrink-0 text-sm font-semibold text-ink-700 tabular-nums">{{ $row['count'] }}</span>
                    </div>
                    <p class="mt-0.5 text-xs text-ink-500">
                        @foreach ($row['topics'] as $label => $count){{ $label }} ({{ $count }})@if (! $loop->last), @endif @endforeach
                    </p>
                </li>
                @endforeach
            </ul>
            @endif
        </x-admin.card>
    </div>
    @endif
</x-layouts.admin>