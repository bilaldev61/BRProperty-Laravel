@php
// Quick views: the three questions someone actually opens this page with.
$view = request('view');
$views = [
'' => ['All', null],
'needs_reply' => ['Needs reply', $needsReplyCount],
'overdue' => ['Overdue', $overdueCount],
];
@endphp

<x-layouts.admin title="Enquiries" heading="Inbox"
    intro="Everything the website forms and the phone agent have collected. Open one to reply, log a call or close it out.">
    <x-slot:actions>
        @if ($needsReplyCount > 0)
        <x-admin.badge tone="brass" class="!px-3 !py-1">{{ $needsReplyCount }} waiting on a reply</x-admin.badge>
        @else
        <x-admin.badge tone="green" class="!px-3 !py-1">Inbox clear</x-admin.badge>
        @endif
    </x-slot:actions>

    {{-- One toolbar: which slice of the inbox, then the filters that narrow it. --}}
    <x-admin.card :padded="false" tone="brass">
        <div class="flex flex-wrap items-center gap-2 p-4">
            @foreach ($views as $value => [$label, $count])
            <a href="{{ request()->fullUrlWithQuery(['view' => $value ?: null, 'page' => null]) }}"
                @class([ 'rounded-full border px-3.5 py-1.5 text-sm font-medium transition' , 'border-ink-900 bg-ink-900 text-sand-100'=> (string) $view === (string) $value,
                'border-ink-200 bg-white text-ink-600 hover:border-ink-400 hover:text-ink-900' => (string) $view !== (string) $value,
                ])>
                {{ $label }}@if ($count !== null) <span class="tabular-nums opacity-70">({{ $count }})</span>@endif
            </a>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-3 border-t border-ink-100 bg-sand-50/60 p-4">
            <input type="hidden" name="view" value="{{ $view }}">

            <label class="sr-only" for="enq-type">Type</label>
            <select id="enq-type" name="type" class="input sm:w-48" onchange="this.form.submit()">
                <option value="">All enquiry types</option>
                @foreach (['property' => 'Property enquiries', 'appraisal' => 'Free appraisals', 'contact' => 'General contact'] as $value => $label)
                <option value="{{ $value }}" @selected(request('type')===$value)>{{ $label }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="enq-priority">Priority</label>
            <select id="enq-priority" name="priority" class="input sm:w-40" onchange="this.form.submit()">
                <option value="">Any priority</option>
                @foreach (\App\Support\EnquiryPriority::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('priority')===$value)>{{ $label }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="enq-status">Status</label>
            <select id="enq-status" name="status" class="input sm:w-40" onchange="this.form.submit()">
                <option value="">Any status</option>
                @foreach (\App\Models\Enquiry::statuses() as $value => $label)
                <option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>
                @endforeach
            </select>

            <label class="flex items-center gap-2 text-sm text-ink-600">
                <input type="checkbox" name="unread" value="1" class="rounded border-ink-300 text-ink-900 focus:ring-brass-500"
                    @checked(request()->boolean('unread')) onchange="this.form.submit()">
                Unread only ({{ $unreadCount }})
            </label>

            <noscript><button type="submit" class="btn-primary">Filter</button></noscript>
        </form>
    </x-admin.card>

    @if ($enquiries->isEmpty())
    <x-empty-state title="No enquiries here" message="Enquiries from the website forms land in this inbox." />
    @else
    <x-admin.card :padded="false">
        <div class="divide-y divide-ink-50">
            @foreach ($enquiries as $enquiry)
            <a href="{{ route('admin.enquiries.show', $enquiry) }}"
                class="flex flex-col gap-2 px-5 py-4 transition hover:bg-sand-50 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2">
                        <x-priority-chip :priority="$enquiry->priority" :reason="$enquiry->priorityReason()" class="!text-[11px]" />

                        <span @class(['truncate', 'font-semibold text-ink-900'=> $enquiry->isOpen(), 'text-ink-700' => ! $enquiry->isOpen()])>
                            {{ $enquiry->name }}
                        </span>

                        <x-admin.badge>{{ $enquiry->typeLabel() }}</x-admin.badge>

                        @if ($enquiry->cameFromCall())
                        <x-admin.badge tone="indigo">Phone call</x-admin.badge>
                        @endif

                        @if ($enquiry->cameFromChat())
                        <x-admin.badge tone="teal">Via assistant</x-admin.badge>
                        @endif

                        @if ($enquiry->isOverdue())
                        <x-admin.badge tone="red">Overdue</x-admin.badge>
                        @endif
                    </p>

                    <p class="mt-1 truncate text-sm text-ink-500">
                        {{ collect([$enquiry->email, $enquiry->phone])->filter()->implode(' · ') }}
                        @if ($enquiry->property) &middot; re: {{ $enquiry->property->title }} @endif
                    </p>

                    @if ($enquiry->message)
                    <p class="mt-1 truncate text-sm text-ink-400">{{ $enquiry->message }}</p>
                    @endif

                    {{-- The rule behind the priority, spelled out. --}}
                    <p class="mt-1.5 text-xs text-ink-400">{{ $enquiry->priorityReason() }}</p>
                </div>

                <div class="flex shrink-0 flex-row items-center gap-3 sm:flex-col sm:items-end sm:gap-1.5">
                    <x-enquiry-status-chip :status="$enquiry->status" class="!text-[11px]" />
                    <span class="text-xs text-ink-400">{{ $enquiry->created_at->format('j M Y, g:ia') }}</span>
                </div>
            </a>
            @endforeach
        </div>
    </x-admin.card>

    <div>{{ $enquiries->links() }}</div>
    @endif
</x-layouts.admin>