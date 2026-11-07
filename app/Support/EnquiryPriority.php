<?php

namespace App\Support;

use App\Models\Enquiry;

/**
 * Works out how urgently an enquiry deserves a call back.
 *
 * Deliberately rule-based rather than clever. Every signal it uses is already
 * captured by the website forms, so the answer is instant, free, identical every
 * time, and - most importantly - explainable: `reasonFor()` gives the agent the
 * sentence behind the label. An agent who cannot see why something is hot stops
 * trusting the label, and an untrusted label is worse than none.
 *
 * The rules read structured fields only. Judging intent from the wording of the
 * message ("I have cash ready", "must move before December") needs a model and
 * is deliberately out of scope here.
 */
class EnquiryPriority
{
    public const HOT  = 'hot';
    public const WARM = 'warm';
    public const COLD = 'cold';

    /** Seller timeframes that mean the agent should be calling today. */
    private const URGENT_TIMEFRAMES = ['ASAP', '1-3 months'];

    /** Seller timeframes that are real, but not this week's problem. */
    private const PATIENT_TIMEFRAMES = ['3-6 months', '6-12 months'];

    public static function for(Enquiry $enquiry): string
    {
        return static::assess($enquiry)[0];
    }

    public static function reasonFor(Enquiry $enquiry): string
    {
        return static::assess($enquiry)[1];
    }

    /**
     * @return array{0: string, 1: string} priority, and the reason behind it
     */
    private static function assess(Enquiry $enquiry): array
    {
        // A lead the phone agent qualified carries its own scored grade, which
        // knows far more than the form fields below can.
        if (filled($enquiry->qualification['grade'] ?? null)) {
            return [$enquiry->qualification['grade'], $enquiry->qualification['summary'] ?? 'Qualified by the phone agent'];
        }

        if ($enquiry->type === 'appraisal') {
            return static::assessSeller($enquiry);
        }

        if ($enquiry->type === 'property') {
            return filled($enquiry->phone)
                ? [self::WARM, 'Buyer asking about a listing, phone number given']
                : [self::COLD, 'Buyer asking about a listing, email only'];
        }

        return [self::COLD, 'General message, no listing attached'];
    }

    /**
     * Appraisal enquiries are people thinking about selling - a potential
     * listing, which is worth far more to the agent than a buyer question. The
     * timeframe they picked is the whole signal.
     */
    private static function assessSeller(Enquiry $enquiry): array
    {
        $timeframe = $enquiry->detail('timeframe');

        if (in_array($timeframe, self::URGENT_TIMEFRAMES, true)) {
            return [self::HOT, "Seller lead, wants to move {$timeframe}"];
        }

        if (in_array($timeframe, self::PATIENT_TIMEFRAMES, true)) {
            return [self::WARM, "Seller lead, timeframe {$timeframe}"];
        }

        return [self::COLD, 'Seller lead, still researching'];
    }

    /** @return array<string, string> value => label, for filter menus. */
    public static function options(): array
    {
        return [
            self::HOT  => 'Hot',
            self::WARM => 'Warm',
            self::COLD => 'Cold',
        ];
    }
}
