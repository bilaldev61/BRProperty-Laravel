<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Property;
use App\Services\Assistant\PropertyAssistant;
use App\Support\Ai\AiUnavailable;
use App\Support\AssistantSuggestions;
use App\Support\PublishedProperty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The chat bubble's two endpoints: pick a conversation back up, and say
 * something in it.
 *
 * A visitor gets one conversation per listing page, plus one for the rest of
 * the site, remembered in their session. No account, no cookie of our own, and
 * no way to read anybody else's chat - the token never leaves the server.
 */
class AssistantController extends Controller
{
    public function show(Request $request, PropertyAssistant $assistant): JsonResponse
    {
        abort_unless($assistant->isAvailable(), 404);

        $property = $this->property($request);
        $conversation = $this->conversation($request, $property);

        return response()->json([
            'messages' => $conversation?->messages
                ->map(fn(ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
                ->values() ?? [],
            'lead' => (bool) $conversation?->isLead(),
            // The starter chips travel with the chat, not with the page: they
            // need the listings table, and the page should not wait on it.
            'suggestions' => app(AssistantSuggestions::class)->for($property),
        ]);
    }

    public function store(Request $request, PropertyAssistant $assistant): JsonResponse
    {
        abort_unless($assistant->isAvailable(), 404);

        $data = $request->validate([
            'message'  => ['required', 'string', 'max:1000'],
            'property' => ['nullable', 'string', 'max:255'],
        ]);

        $property = $this->property($request);
        $conversation = $this->conversation($request, $property) ?? $this->start($request, $property);

        try {
            $reply = $assistant->reply($conversation, trim($data['message']));
        } catch (AiUnavailable $e) {
            // Belt and braces - reply() itself answers from the rules when the
            // model is down. Whatever is said is saved too, so the visitor's
            // question is never left hanging in the transcript.
            report($e);

            $reply = $assistant->fallback();
            $conversation->messages()->create([
                'role'        => ChatMessage::ASSISTANT,
                'content'     => $reply,
                'answered_by' => ChatMessage::BY_FALLBACK,
            ]);
        }

        return response()->json(['reply' => $reply, 'lead' => $conversation->refresh()->isLead()]);
    }

    /** The listing the chat is about. Unpublished listings are simply not one. */
    private function property(Request $request): ?Property
    {
        return app(PublishedProperty::class)->bySlug($request->input('property'));
    }

    private function conversation(Request $request, ?Property $property): ?ChatConversation
    {
        $token = $request->session()->get($this->sessionKey($property));

        return $token ? ChatConversation::with('messages', 'property')->where('token', $token)->first() : null;
    }

    private function start(Request $request, ?Property $property): ChatConversation
    {
        $conversation = ChatConversation::create(['property_id' => $property?->id]);

        $request->session()->put($this->sessionKey($property), $conversation->token);

        return $conversation;
    }

    private function sessionKey(?Property $property): string
    {
        return 'assistant.' . ($property?->id ?? 'site');
    }
}
