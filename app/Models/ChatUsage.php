<?php

namespace App\Models;

use App\Support\Ai\ModelCost;
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

    /** Estimated dollars for this request, or null when the model is unpriced. */
    public function cost(): ?float
    {
        return ModelCost::estimate(
            (string) $this->model,
            (int) $this->prompt_tokens,
            (int) $this->cached_tokens,
            (int) $this->cache_write_tokens,
            (int) $this->completion_tokens,
        );
    }

    /** Milliseconds under a second, one decimal above it - a wait reads better than 3465ms. */
    public function latencyLabel(): string
    {
        return $this->latency_ms >= 1000
            ? number_format($this->latency_ms / 1000, 1) . 's'
            : $this->latency_ms . 'ms';
    }
}
