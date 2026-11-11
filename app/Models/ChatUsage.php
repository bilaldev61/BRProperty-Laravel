<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one model request cost - see the chat_usage migration.
 *
 * Rows are written per request, not per turn, so the tool rounds a reply took
 * are visible: a turn that stops at round 0 is one request, and one that calls
 * a tool carries on to round 1.
 */
class ChatUsage extends Model
{
    /** Eloquent would pluralise this to chat_usages. */
    protected $table = 'chat_usage';

    protected $guarded = [];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    /** The tool names this request called, as a list. */
    public function toolNames(): array
    {
        return filled($this->tools) ? explode(',', (string) $this->tools) : [];
    }
}
