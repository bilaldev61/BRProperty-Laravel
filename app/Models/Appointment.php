<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A viewing or meeting with a lead - booked by the phone agent or the agent. */
class Appointment extends Model
{
    public const REQUESTED = 'requested';
    public const CONFIRMED = 'confirmed';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const NO_SHOW   = 'no_show';

    /** Statuses that hold the slot. */
    public const ACTIVE = [self::REQUESTED, self::CONFIRMED];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->active()->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    /** "Sat 11 Oct, 3:00pm" in the agent's timezone. */
    public function whenLabel(): string
    {
        return $this->starts_at->copy()->setTimezone(config('agent.appointments.timezone'))->format('D j M, g:ia');
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::REQUESTED => 'Requested',
            self::CONFIRMED => 'Confirmed',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW   => 'No-show',
        ];
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? ucfirst($this->status);
    }
}
