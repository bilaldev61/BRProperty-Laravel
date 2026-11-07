<x-layouts.admin :title="'Enquiry from '.$enquiry->name" :heading="$enquiry->typeLabel()"
    :intro="'Received '.$enquiry->created_at->format('l j F Y, g:ia').'. Everything this person has been sent is below, so the next reply never contradicts it.'">
    <x-slot:actions>
        <a href="{{ route('admin.enquiries.index') }}" class="text-sm font-medium text-ink-500 hover:text-ink-900">&larr; All enquiries</a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr] lg:items-start">
        <x-admin.card tone="brass">
            <div class="flex flex-wrap items-center gap-2 border-b border-ink-100 pb-4">
                <x-priority-chip :priority="$enquiry->priority" />
                <x-enquiry-status-chip :status="$enquiry->status" />
                @if ($enquiry->isOverdue())
                <x-admin.badge tone="red">Follow-up overdue</x-admin.badge>
                @endif
            </div>

            <h2 class="mt-5 font-display text-2xl text-ink-900">{{ $enquiry->name }}</h2>
            <p class="mt-1 text-sm text-ink-500">{{ $enquiry->priorityReason() }}</p>

            @if ($enquiry->message)
            <div class="mt-6 rounded-lg bg-sand-50 p-5">
                <p class="text-[15px] leading-relaxed whitespace-pre-line text-ink-700">{{ $enquiry->message }}</p>
            </div>
            @else
            <p class="mt-6 text-sm text-ink-400">No message was included.</p>
            @endif

            @if ($enquiry->qualification)
            @include('admin.enquiries._qualification', ['qualification' => $enquiry->qualification])
            @endif

            @if (filled($enquiry->requirements))
            @include('admin.enquiries._requirements', ['requirements' => \App\Services\Leads\Requirements::stated($enquiry->requirements), 'matches' => $matches])
            @endif

            @if ($enquiry->calls->isNotEmpty() || $enquiry->appointments->isNotEmpty())
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                @if ($enquiry->calls->isNotEmpty())
                <div class="rounded-lg border border-ink-100 p-4">
                    <h3 class="text-sm font-semibold text-ink-900">Calls</h3>
                    <ul class="mt-2 space-y-1.5 text-sm">
                        @foreach ($enquiry->calls as $call)
                        <li><a href="{{ route('admin.calls.show', $call) }}" class="text-brass-600 hover:underline">{{ $call->started_at?->setTimezone(config('agent.appointments.timezone'))->format('j M, g:ia') ?? 'Call' }}</a> <span class="text-ink-400">&middot; {{ $call->durationLabel() }}</span></li>
                        @endforeach
                    </ul>
                </div>
                @endif
                @if ($enquiry->appointments->isNotEmpty())
                <div class="rounded-lg border border-ink-100 p-4">
                    <h3 class="text-sm font-semibold text-ink-900">Viewings</h3>
                    <ul class="mt-2 space-y-1.5 text-sm text-ink-700">
                        @foreach ($enquiry->appointments as $appointment)
                        <li>{{ $appointment->whenLabel() }} <span class="text-ink-400">&middot; {{ $appointment->statusLabel() }}@if ($appointment->property) &middot; {{ $appointment->property->title }}@endif</span></li>
                        @endforeach
                    </ul>
                    <a href="{{ route('admin.appointments.index') }}" class="mt-2 inline-block text-xs font-medium text-brass-600 hover:underline">Manage viewings</a>
                </div>
                @endif
            </div>
            @endif

            @if ($enquiry->auto_reply)
            {{-- Exactly what the client was emailed, so the follow-up never contradicts it. --}}
            <details class="mt-6 rounded-lg border border-brass-200 bg-brass-50/50 p-4" open>
                <summary class="cursor-pointer text-sm font-semibold text-ink-900">
                    Automatic reply sent {{ $enquiry->confirmation_sent_at?->format('j M, g:ia') }}
                </summary>
                <p class="mt-1 text-xs text-ink-500">Written by AI from your listing records and fact-checked before sending. This is what they received.</p>
                <p class="mt-3 text-[15px] leading-relaxed whitespace-pre-line text-ink-700">{{ $enquiry->auto_reply }}</p>
            </details>
            @endif

            @if ($enquiry->conversation)
            {{-- The chat it came out of: what they asked, and what they were told. --}}
            <details class="mt-6 rounded-lg border border-teal-100 bg-teal-50/40 p-4" open>
                <summary class="cursor-pointer text-sm font-semibold text-ink-900">
                    Assistant chat &middot; {{ $enquiry->conversation->visitor_messages }} {{ Str::plural('message', $enquiry->conversation->visitor_messages) }}
                </summary>
                <p class="mt-1 text-xs text-ink-500">The message above is the assistant's summary. This is what was actually said.</p>
                <div class="mt-4 max-h-[28rem] overflow-y-auto pr-1">
                    @include('admin.assistant._transcript', ['messages' => $enquiry->conversation->messages])
                </div>
            </details>
            @endif

            @if (filled($enquiry->details))
            <dl class="mt-6 grid gap-4 border-t border-ink-100 pt-6 sm:grid-cols-2">
                @foreach ($enquiry->details as $key => $value)
                <div>
                    <dt class="text-xs tracking-wide text-ink-400 uppercase">{{ str_replace('_', ' ', $key) }}</dt>
                    <dd class="mt-0.5 text-[15px] text-ink-800">{{ $value }}</dd>
                </div>
                @endforeach
            </dl>
            @endif

            @if ($enquiry->property)
            <div class="mt-6 flex items-center gap-4 rounded-lg border border-ink-100 p-4">
                <img src="{{ $enquiry->property->heroImageUrl() }}" alt="" loading="lazy" decoding="async" class="h-14 w-20 shrink-0 rounded-md object-cover">
                <div class="min-w-0 flex-1">
                    <p class="text-xs tracking-wide text-ink-400 uppercase">Enquiring about</p>
                    <p class="truncate font-medium text-ink-900">{{ $enquiry->property->title }}</p>
                    <p class="text-sm text-ink-500">{{ $enquiry->property->fullAddress() }}</p>
                </div>
                <a href="{{ route('admin.properties.edit', $enquiry->property) }}" class="shrink-0 text-sm font-medium text-brass-600 hover:underline">Open</a>
            </div>
            @endif
        </x-admin.card>

        <aside class="space-y-4">
            {{-- Where this sits in the workflow, and when to chase it. --}}
            @can('enquiries.manage')
            <x-admin.card title="Progress" tone="indigo">
                <form method="POST" action="{{ route('admin.enquiries.update', $enquiry) }}" class="space-y-4">
                    @csrf @method('PATCH')

                    <div>
                        <label for="status" class="block text-sm font-medium text-ink-700">Status</label>
                        <select id="status" name="status" class="input mt-1 w-full">
                            @foreach (\App\Models\Enquiry::statuses() as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $enquiry->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="follow_up_at" class="block text-sm font-medium text-ink-700">Chase on</label>
                        <input type="date" id="follow_up_at" name="follow_up_at" class="input mt-1 w-full"
                            value="{{ old('follow_up_at', $enquiry->follow_up_at?->toDateString()) }}">
                        <p class="mt-1 text-xs text-ink-400">Leave empty if nothing is owed. Cleared when you close the enquiry.</p>
                        @error('follow_up_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="btn-primary w-full">Save progress</button>
                </form>
            </x-admin.card>
            @endcan

            @can('enquiries.manage')
            <x-admin.card title="Reply" tone="teal">
                @if ($aiAvailable)
                <div class="border-b border-ink-100 pb-5">
                    <x-island name="ReplyDrafter" :props="['endpoint' => route('admin.enquiries.draft', $enquiry)]" />
                </div>
                @endif

                <div class="mt-4 space-y-2">
                    @if ($enquiry->email)
                    <a href="mailto:{{ $enquiry->email }}?subject={{ urlencode('Re: your enquiry with '.config('agent.name')) }}" class="btn-primary w-full">
                        Email {{ Str::before($enquiry->email, '@') }}
                    </a>
                    @endif
                    @if ($enquiry->phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $enquiry->phone) }}" class="btn-outline w-full">Call {{ $enquiry->phone }}</a>
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $enquiry->phone) }}" target="_blank" rel="noopener noreferrer"
                        class="btn w-full bg-[#25D366] text-ink-900 hover:brightness-95">WhatsApp</a>
                    @endif
                </div>

                <dl class="mt-6 space-y-3 border-t border-ink-100 pt-5 text-sm">
                    @if ($enquiry->email)
                    <div>
                        <dt class="text-ink-400">Email</dt>
                        <dd class="break-all text-ink-800">{{ $enquiry->email }}</dd>
                    </div>
                    @endif
                    @if ($enquiry->phone)
                    <div>
                        <dt class="text-ink-400">Phone</dt>
                        <dd class="text-ink-800">{{ $enquiry->phone }}</dd>
                    </div>
                    @endif
                    <div>
                        <dt class="text-ink-400">Type</dt>
                        <dd class="text-ink-800">{{ $enquiry->typeLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-400">Came in via</dt>
                        <dd class="text-ink-800">{{ $enquiry->sourceLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-400">First email</dt>
                        <dd class="text-ink-800">
                            @if ($enquiry->confirmation_sent_at)
                            {{ $enquiry->auto_reply ? 'AI reply' : 'Template confirmation' }} sent {{ $enquiry->confirmation_sent_at->format('j M Y, g:ia') }}
                            @elseif (! $enquiry->email)
                            Not sent - no email address
                            @elseif (! \App\Jobs\SendEnquiryConfirmation::mailIsConfigured())
                            Not sent - email is not set up on this server yet
                            @else
                            Not sent
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($enquiry->email)
                <form method="POST" action="{{ route('admin.enquiries.confirmation', $enquiry) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-sm font-medium text-brass-600 hover:underline">
                        {{ $enquiry->confirmation_sent_at ? 'Resend that email' : 'Send confirmation email' }}
                    </button>
                </form>
                @endif
            </x-admin.card>
            @endcan

            @can('enquiries.delete')
            <form method="POST" action="{{ route('admin.enquiries.destroy', $enquiry) }}"
                data-confirm="This enquiry will be permanently removed."
                data-confirm-title="Delete this enquiry?">
                @csrf @method('DELETE')
                <button type="submit" class="btn-outline w-full !border-red-200 text-red-600 hover:!bg-red-600 hover:!text-white">
                    Delete enquiry
                </button>
            </form>
            @endcan
        </aside>
    </div>
</x-layouts.admin>