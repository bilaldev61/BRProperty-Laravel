<?php

namespace App\Services\Assistant\Kit;

use App\Models\ChatConversation;
use App\Models\ChatUnansweredQuestion;
use AssistantKit\Contracts\ConversationStore;
use AssistantKit\Value\Conversation;
use Illuminate\Support\Str;

/**
 * The Kit's chat in the site's own tables.
 *
 * The Kit works on one value object per turn; this adapter loads it from
 * chat_messages and, on save, writes back only the messages the Kit appended -
 * so the admin transcript, the lead link and the retention pruner keep seeing
 * exactly the rows they always did.
 */
class EloquentChatStore implements ConversationStore
{
    /** How many messages were already stored when the turn began. */
    private int $persisted;

    public function __construct(private readonly ChatConversation $conversation)
    {
        $this->persisted = $conversation->messages()->count();
    }

    public function load(string $id, ?string $itemSlug = null): Conversation
    {
        $conversation = new Conversation(
            (string) $this->conversation->id,
            $this->conversation->property?->slug,
            (int) $this->conversation->visitor_messages,
        );

        $conversation->leadRef = $this->conversation->enquiry_id !== null
            ? (string) $this->conversation->enquiry_id
            : null;

        foreach ($this->conversation->messages()->get(['role', 'content', 'answered_by']) as $message) {
            $conversation->messages[] = [
                'role'        => (string) $message->role,
                'content'     => (string) $message->content,
                'answered_by' => $message->answered_by,
            ];
        }

        return $conversation;
    }

    public function save(Conversation $conversation): void
    {
        foreach (array_slice($conversation->messages, $this->persisted) as $message) {
            $this->conversation->messages()->create([
                'role'        => $message['role'],
                'content'     => $message['content'],
                'answered_by' => $message['answered_by'],
            ]);
        }

        $this->persisted = count($conversation->messages);

        $this->conversation->update([
            'visitor_messages' => $conversation->visitorMessages,
            'last_message_at'  => now(),
        ]);

        $this->conversation->refresh();
    }

    public function recordQuestion(Conversation $conversation, string $question, ?string $itemSlug = null): void
    {
        ChatUnansweredQuestion::create([
            'chat_conversation_id' => $this->conversation->id,
            'property_id'          => $this->conversation->property_id,
            'topic'                => 'other',
            'question'             => Str::limit($question, 500, ''),
        ]);
    }

    public function questions(Conversation $conversation): array
    {
        return $this->conversation->unansweredQuestions()
            ->get(['question'])
            ->map(fn(ChatUnansweredQuestion $row) => [
                'question' => (string) $row->question,
                'item'     => $this->conversation->property?->slug,
            ])
            ->values()
            ->all();
    }
}
