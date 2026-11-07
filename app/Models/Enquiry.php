<?php

namespace App\Models;

use App\Support\EnquiryPriority;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enquiry extends Model
{
    use HasFactory;

    public const STATUS_NEW         = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_REPLIED     = 'replied';
    public const STATUS_CLOSED      = 'closed';

    public const SOURCE_FORM = 'form';
    public const SOURCE_CHAT = 'chat';
    public const SOURCE_VOICE = 'voice';

    /** Statuses that still owe the sender something. */
    public const OPEN_STATUSES = [self::STATUS_NEW, self::STATUS_IN_PROGRESS];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'details'      => 'array',
            'requirements' => 'array',
            'qualification' => 'array',
            'read_at'      => 'datetime',
            'follow_up_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Priority is derived from fields the sender filled in, so it is settled
        // the moment the enquiry arrives and never needs recalculating.
        // Kept in one comparable form so a repeat caller finds their lead.
        static::saving(function (Enquiry $enquiry) {
            if ($enquiry->isDirty('phone')) {
                $enquiry->phone_normalized = Phone::normalize($enquiry->phone);
            }
        });

        static::creating(function (Enquiry $enquiry) {
            $enquiry->priority ??= EnquiryPriority::for($enquiry);
            $enquiry->status ??= self::STATUS_NEW;
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** Phone calls with this lead, newest first. */
    public function calls(): HasMany
    {
        return $this->hasMany(Call::class)->latest('started_at');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->orderBy('starts_at');
    }

    /** The assistant chat this enquiry came out of, if it did. */
    public function conversation(): HasOne
    {
        return $this->hasOne(ChatConversation::class);
    }

    /** One value out of the appraisal `details` blob, without the null dance. */
    public function detail(string $key): ?string
    {
        $value = $this->details[$key] ?? null;

        return $value === null ? null : (string) $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /** Still owes the sender a reply. */
    public function scopeNeedsReply(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    /** Follow-up date has passed and the enquiry is still open. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->needsReply()
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now());
    }

    /** Hot first, then warm, then cold - the order the agent should work in. */
    public function scopeByUrgency(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case priority when ? then 0 when ? then 1 else 2 end", [
                EnquiryPriority::HOT,
                EnquiryPriority::WARM,
            ])
            ->latest();
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    public function cameFromCall(): bool
    {
        return $this->source === self::SOURCE_VOICE;
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_CHAT  => 'Website assistant',
            self::SOURCE_VOICE => 'Phone agent',
            default            => 'Website form',
        };
    }

    public function cameFromChat(): bool
    {
        return $this->source === self::SOURCE_CHAT;
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->follow_up_at !== null
            && $this->follow_up_at->isPast();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'property'  => 'Property enquiry',
            'appraisal' => 'Free appraisal',
            default     => 'General contact',
        };
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? ucfirst($this->status);
    }

    /** Why this enquiry carries the priority it does, in one sentence. */
    public function priorityReason(): string
    {
        return EnquiryPriority::reasonFor($this);
    }

    /** @return array<string, string> value => label, in workflow order. */
    public static function statuses(): array
    {
        return [
            self::STATUS_NEW         => 'New',
            self::STATUS_IN_PROGRESS => 'In progress',
            self::STATUS_REPLIED     => 'Replied',
            self::STATUS_CLOSED      => 'Closed',
        ];
    }
}
