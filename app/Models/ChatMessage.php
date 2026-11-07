<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a chat, as plain text.
 *
 * Only what the visitor saw is stored - never the model's tool calls or
 * reasoning. That is also what is resent to the model on the next turn.
 */
class ChatMessage extends Model
{
    public const USER      = 'user';
    public const ASSISTANT = 'assistant';

    /** Who wrote an assistant reply - see the usage migration. */
    public const BY_AI          = 'ai';
    public const BY_LIMIT       = 'limit';
    public const BY_RULE_PREFIX = 'rule:';

    protected $guarded = [];

    public function answeredByRule(): bool
    {
        return str_starts_with((string) $this->answered_by, self::BY_RULE_PREFIX);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }
}
