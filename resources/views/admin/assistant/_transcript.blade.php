{{-- A chat as the visitor saw it. Used on the chat page and on the enquiry it became. --}}
<ol class="space-y-3">
    @foreach ($messages as $message)
        <li @class(['flex', 'justify-end' => $message->role === 'user'])>
            <div @class([
                'max-w-[85%] rounded-2xl px-4 py-2.5 text-[15px] leading-relaxed whitespace-pre-line',
                'rounded-br-md bg-ink-900 text-sand-50' => $message->role === 'user',
                'rounded-bl-md bg-sand-100 text-ink-800' => $message->role !== 'user',
            ])>
                <span class="sr-only">{{ $message->role === 'user' ? 'Visitor' : 'Assistant' }}:</span>
                {{ $message->content }}
                <span @class(['mt-1 block text-[11px]', 'text-ink-300' => $message->role === 'user', 'text-ink-400' => $message->role !== 'user'])>
                    {{ $message->created_at->format('g:ia') }}
                    @if ($message->answeredByRule())
                        &middot; from listing data, no AI
                    @elseif ($message->answered_by === 'ai')
                        &middot; AI{{ $message->tokens ? ', '.number_format($message->tokens).' tokens' : '' }}
                    @endif
                </span>
            </div>
        </li>
    @endforeach
</ol>
