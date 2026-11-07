<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A phone call handled by the voice agent, as the provider reported it. */
class Call extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'ended_at'     => 'datetime',
            'in_voicemail' => 'boolean',
            'analysis'     => 'array',
            'outcome'      => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function durationLabel(): string
    {
        if ($this->duration_seconds === null) {
            return '—';
        }

        return sprintf('%d:%02d', intdiv($this->duration_seconds, 60), $this->duration_seconds % 60);
    }

    public function costLabel(): ?string
    {
        return $this->cost_cents === null ? null : '$'.number_format($this->cost_cents / 100, 2);
    }
}
