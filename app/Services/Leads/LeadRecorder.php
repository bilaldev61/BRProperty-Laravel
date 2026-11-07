<?php

namespace App\Services\Leads;

use App\Models\Enquiry;
use App\Support\Phone;

/**
 * Finds a caller's open lead by phone number, or starts one.
 *
 * Someone who rings twice in a week is one lead with two calls, not two
 * leads - otherwise the inbox fills with duplicates and the agent calls the
 * same person back twice. Only a lead still open and recent enough is reused;
 * a closed one, or one from months ago, means a new piece of business.
 */
class LeadRecorder
{
    public function find(?string $phone): ?Enquiry
    {
        $normalized = Phone::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        return Enquiry::where('phone_normalized', $normalized)
            ->needsReply()
            ->where('updated_at', '>=', now()->subDays((int) config('integrations.calls.merge_window_days')))
            ->latest('id')
            ->first();
    }

    /** @param array<string, mixed> $attributes used only when the lead is new */
    public function findOrStart(?string $phone, ?string $name, array $attributes = []): Enquiry
    {
        $lead = $this->find($phone);

        if ($lead) {
            // A name heard on this call beats the placeholder from an earlier one.
            if (filled($name) && $lead->name === self::UNKNOWN_NAME) {
                $lead->name = $name;
            }

            return $lead;
        }

        return new Enquiry(array_merge([
            'type'   => 'contact',
            'source' => Enquiry::SOURCE_VOICE,
            'name'   => filled($name) ? $name : self::UNKNOWN_NAME,
            'phone'  => $phone,
        ], $attributes));
    }

    public const UNKNOWN_NAME = 'Phone caller';
}
