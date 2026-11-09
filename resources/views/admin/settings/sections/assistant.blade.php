<form id="settings-form" method="POST" action="{{ route('admin.settings.update', 'assistant') }}" class="space-y-5">
    @csrf @method('PUT')

    <div class="grid gap-5 sm:grid-cols-2">
        <x-field label="Messages kept in context" name="history" required
            hint="How much of the conversation is resent to the model on every turn. 12 means six exchanges. This is the largest part of each call's cost.">
            <input id="history" name="history" type="number" min="4" max="24" step="1" class="input"
                value="{{ old('history', $chat['history']) }}" required>
        </x-field>

        <x-field label="Messages before hand-off" name="max_messages" required
            hint="After this many visitor messages the assistant stops answering and offers the phone instead.">
            <input id="max_messages" name="max_messages" type="number" min="5" max="100" step="1" class="input"
                value="{{ old('max_messages', $chat['max_messages']) }}" required>
        </x-field>

        <x-field label="Delete old chats after (days)" name="retention_days" required
            hint="Chats that never became an enquiry are removed after this. A chat that became an enquiry is kept.">
            <input id="retention_days" name="retention_days" type="number" min="7" max="730" step="1" class="input"
                value="{{ old('retention_days', $chat['retention_days']) }}" required>
        </x-field>

        <x-field label="Daily cap for the whole site" name="daily_limit" required
            hint="Total messages every visitor together may send in a day. Past it, the assistant points people to your phone. This is the ceiling on the bill and on abuse.">
            <input id="daily_limit" name="daily_limit" type="number" min="50" max="100000" step="10" class="input"
                value="{{ old('daily_limit', $chat['daily_limit']) }}" required>
        </x-field>
    </div>

    <div class="space-y-3 border-t border-ink-100 pt-4">
        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="model_enabled" value="1"
                class="mt-0.5 rounded border-ink-300 text-ink-900 focus:ring-brass-500"
                @checked(old('model_enabled', $chat['model_enabled']))>
            <span><span class="font-medium text-ink-900">Use the AI model</span><br>
                <span class="text-ink-500">Off means every reply comes from the rule layers - listings, prices, areas,
                    opening hours, contact details. Free, and nothing is invented. Judgement questions then go to
                    your phone number instead of an answer.</span></span>
        </label>

        <label class="flex items-start gap-3 text-sm">
            <input type="checkbox" name="quick_answers" value="1"
                class="mt-0.5 rounded border-ink-300 text-ink-900 focus:ring-brass-500"
                @checked(old('quick_answers', $chat['quick_answers']))>
            <span><span class="font-medium text-ink-900">Answer simple questions from your data first</span><br>
                <span class="text-ink-500">Price, size, availability, hours. Leave this on; switching it off only
                    makes the model answer things it already answers for free.</span></span>
        </label>
    </div>

    <div class="rounded-lg border border-ink-100 bg-sand-50 p-4">
        <p class="text-xs font-semibold tracking-wide text-ink-500 uppercase">How it reads right now</p>
        <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
            <div class="flex justify-between gap-4 border-b border-ink-100 pb-1.5">
                <dt class="text-ink-500">Model</dt>
                <dd class="font-semibold text-ink-900">{{ $chat['model_enabled'] ? 'on' : 'off - rules only' }}</dd>
            </div>
            <div class="flex justify-between gap-4 border-b border-ink-100 pb-1.5">
                <dt class="text-ink-500">Context per turn</dt>
                <dd class="font-semibold text-ink-900">{{ $chat['history'] }} messages</dd>
            </div>
            <div class="flex justify-between gap-4 border-b border-ink-100 pb-1.5">
                <dt class="text-ink-500">Hand-off after</dt>
                <dd class="font-semibold text-ink-900">{{ $chat['max_messages'] }} messages</dd>
            </div>
            <div class="flex justify-between gap-4 border-b border-ink-100 pb-1.5">
                <dt class="text-ink-500">Chats kept</dt>
                <dd class="font-semibold text-ink-900">{{ $chat['retention_days'] }} days</dd>
            </div>
            <div class="flex justify-between gap-4 border-b border-ink-100 pb-1.5">
                <dt class="text-ink-500">Site-wide daily cap</dt>
                <dd class="font-semibold text-ink-900">{{ number_format($chat['daily_limit']) }} messages</dd>
            </div>
            <div class="flex justify-between gap-4 border-b border-ink-100 pb-1.5">
                <dt class="text-ink-500">Rules answer first</dt>
                <dd class="font-semibold text-ink-900">{{ $chat['quick_answers'] ? 'yes' : 'no' }}</dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-ink-400">Save to update this preview.</p>
    </div>

    <div class="rounded-lg border-l-4 border-brass-500 bg-brass-50 p-4 text-sm text-ink-700">
        <strong class="font-semibold">The provider, model name and API key are not editable here.</strong>
        A typo in any of them takes the chat offline instead of making it cheaper, so they stay in
        <code class="font-mono text-xs">config/ai.php</code> and <code class="font-mono text-xs">.env</code>.
        Every per-visitor limit (8 messages a minute) lives in code next to them.
    </div>
</form>