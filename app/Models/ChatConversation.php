<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatConversation extends Model
{
    use Prunable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $conversation) {
            $conversation->token ??= (string) Str::uuid();
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    public function unansweredQuestions(): HasMany
    {
        return $this->hasMany(ChatUnansweredQuestion::class);
    }

    /**
     * Chats that never became an enquiry are anonymous browsing - there is no
     * reason to keep them past the retention window. A chat attached to an
     * enquiry is part of that client's record and lives as long as it does.
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('enquiry_id')
            ->where('updated_at', '<', now()->subDays((int) config('ai.chat.retention_days')));
    }

    public function hasReachedLimit(): bool
    {
        return $this->visitor_messages >= (int) config('ai.chat.max_messages');
    }

    public function isLead(): bool
    {
        return $this->enquiry_id !== null;
    }

    /** The chat as plain text, for the agent and for the reply drafter. */
    public function transcript(): string
    {
        return $this->messages
            ->map(fn (ChatMessage $m) => ($m->role === ChatMessage::USER ? 'Visitor' : 'Assistant').': '.$m->content)
            ->implode("\n\n");
    }
}
