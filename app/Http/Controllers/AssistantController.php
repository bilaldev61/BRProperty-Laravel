<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Property;
use App\Services\Assistant\PropertyAssistant;
use App\Support\Ai\AiUnavailable;
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

        $conversation = $this->conversation($request, $this->property($request));

        return response()->json([
            'messages' => $conversation?->messages
                ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
                ->values() ?? [],
            'lead' => (bool) $conversation?->isLead(),
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
            // The visitor still gets an answer that moves them forward - the
            // agent's number - rather than an error in a chat window.
            report($e);

            return response()->json(['reply' => $assistant->fallback(), 'lead' => $conversation->isLead()]);
        }

        return response()->json(['reply' => $reply, 'lead' => $conversation->refresh()->isLead()]);
    }

    /** The listing the chat is about. Unpublished listings are simply not one. */
    private function property(Request $request): ?Property
    {
        $slug = $request->input('property');

        return filled($slug) && is_string($slug)
            ? Property::published()->where('slug', $slug)->first()
            : null;
    }

    private function conversation(Request $request, ?Property $property): ?ChatConversation
    {
        $token = $request->session()->get($this->sessionKey($property));

        return $token ? ChatConversation::with('messages')->where('token', $token)->first() : null;
    }

    private function start(Request $request, ?Property $property): ChatConversation
    {
        $conversation = ChatConversation::create(['property_id' => $property?->id]);

        $request->session()->put($this->sessionKey($property), $conversation->token);

        return $conversation;
    }

    private function sessionKey(?Property $property): string
    {
        return 'assistant.'.($property?->id ?? 'site');
    }
}
