<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public const BY_FALLBACK    = 'fallback';
    public const BY_LEAD        = 'lead';
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

    /** One row per model request behind this reply - none for a rule reply. */
    public function usage(): HasMany
    {
        return $this->hasMany(ChatUsage::class)->orderBy('round');
    }
}
