<?php

namespace App\Services\Leads;

use App\Models\Call;
use App\Support\EnquiryPriority;

/**
 * Scores how ready a lead is to transact, 0-100, with the reason for every
 * point.
 *
 * Deliberately rule-based, like EnquiryPriority: instant, free, identical
 * every time, and explainable. The agent sees "+18 wants to buy within 1-3
 * months" next to the score, not a number from nowhere - and an agent who can
 * see why a lead is hot keeps trusting the label.
 *
 * The grade feeds the existing hot / warm / cold priority, so phone leads sort
 * into the same inbox order as everything else.
 */
class LeadQualifier
{
    public const HOT_FROM = 65;

    public const WARM_FROM = 35;

    private const TIMELINE_POINTS = [
        'asap'          => [25, 'wants to move as soon as possible'],
        '1_3_months'    => [18, 'wants to move within 1-3 months'],
        '3_6_months'    => [8, 'wants to move within 3-6 months'],
        '6_plus_months' => [3, 'timeline is 6+ months away'],
        'browsing'      => [0, 'just browsing for now'],
    ];

    /**
     * @param  array<string, mixed>  $r  requirements
     * @return array{score: int, grade: string, summary: string, reasons: list<array{0: string, 1: int}>, qualified_at: string}
     */
    public function qualify(array $r, ?Call $call, int $matches, bool $appointmentBooked = false): array
    {
        if ($disqualified = $this->disqualified($call)) {
            return $this->result(0, [[$disqualified, 0]]);
        }

        $reasons = [];
        $add = function (int $points, string $why) use (&$reasons) {
            $reasons[] = [$why, $points];
        };

        $add(10, 'reachable on the phone number they called from');
        if (filled($r['name'] ?? null)) {
            $add(5, 'gave their name');
        }

        match ($r['intent'] ?? 'unknown') {
            'sell'  => $add(30, 'wants to sell - a potential listing'),
            'buy'   => $add(10, 'wants to buy'),
            'rent'  => $add(0, 'looking to rent, not buy'),
            default => $add(0, 'did not say whether buying or selling'),
        };

        if (isset(self::TIMELINE_POINTS[$r['timeline'] ?? ''])) {
            [$points, $why] = self::TIMELINE_POINTS[$r['timeline']];
            $add($points, $why);
        } else {
            $add(0, 'no timeline given');
        }

        if (($r['intent'] ?? null) !== 'sell') {
            if (($r['budget_max'] ?? null) || ($r['budget_min'] ?? null)) {
                $add(12, 'gave a budget');
            } else {
                $add(0, 'no budget given');
            }

            if ($r['areas'] ?? []) {
                $add(5, 'named the areas they want');
            }

            if ($matches > 0) {
                $add(10, $matches.' current '.($matches === 1 ? 'listing fits' : 'listings fit'));
            } elseif (($r['intent'] ?? null) === 'buy') {
                $add(0, 'nothing listed fits yet');
            }

            match ($r['payment'] ?? 'unknown') {
                'cash'         => $add(10, 'paying cash'),
                'loan'         => $add(4, 'needs a loan'),
                'installments' => $add(4, 'wants installments'),
                default        => null,
            };
        }

        if ($appointmentBooked) {
            $add(15, 'booked a viewing');
        } elseif ($r['appointment_requested'] ?? false) {
            $add(12, 'asked for a viewing or meeting');
        }

        if (($call?->sentiment) === 'Negative') {
            $add(-5, 'sounded unhappy on the call');
        }

        return $this->result(array_sum(array_column($reasons, 1)), $reasons);
    }

    private function disqualified(?Call $call): ?string
    {
        if (! $call) {
            return null;
        }

        if ($call->in_voicemail) {
            return 'went to voicemail - no conversation';
        }

        if ($call->duration_seconds !== null && $call->duration_seconds < (int) config('integrations.calls.min_qualifying_seconds')) {
            return 'call too short to qualify';
        }

        return null;
    }

    private function result(int $score, array $reasons): array
    {
        $score = max(0, min(100, $score));

        $grade = match (true) {
            $score >= self::HOT_FROM  => EnquiryPriority::HOT,
            $score >= self::WARM_FROM => EnquiryPriority::WARM,
            default                   => EnquiryPriority::COLD,
        };

        // The two strongest reasons, for the one-line summary in the inbox.
        $top = collect($reasons)->sortByDesc(fn ($r) => $r[1])->take(2)->pluck(0)->implode(', ');

        return [
            'score'        => $score,
            'grade'        => $grade,
            'summary'      => 'Phone lead scored '.$score.'/100: '.$top,
            'reasons'      => $reasons,
            'qualified_at' => now()->toIso8601String(),
        ];
    }
}
