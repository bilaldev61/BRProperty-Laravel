<?php

namespace App\Services\Assistant;

use App\Models\ChatConversation;
use Illuminate\Support\Str;

/**
 * A deferral, read from the reply rather than taken on trust.
 *
 * The model used to file these through a tool, which cost a second request on
 * every deferral and only worked if the model remembered to call it. Reading
 * the reply instead makes the record the app's own: a question filed, and the
 * visitor given a way back, whether or not the model cooperated.
 */
class DeferralLog
{
    /** Most specific first: "handover date" belongs to construction, not possession. */
    private const TOPICS = [
        'construction'      => '/\b(completion|construction|developer|handover date|delay(?:ed)?|building work)\b/i',
        'possession'        => '/\b(?:handover|possession|move in|moving in|vacant|ready to move|closing|close(?! to))\b/i',
        'paperwork'         => '/\b(title deed|deed|noc|transfer|contract|paperwork|documents?|permit|registration)\b/i',
        'charges'           => '/\b(service charge|maintenance|dewa|chiller|utilit|bills?|fees?)\b/i',
        'financing'         => '/\b(mortgage|loan|financ|payment plan|instal?ments?|bank)\b/i',
        'price_negotiation' => '/\b(negotiat|discount|flexib|lower|reduce|best price|offer)\b/i',
        'viewing'           => '/\b(viewing|view |visit|showing|tour|appointment)\b/i',
        'location'          => '/\b(location|nearby|close to|distance|metro|school|beach)\b/i',
    ];

    /** Wording a reply uses when it hands the question to the team. */
    private const DEFERRAL = '/\b(?:confirm|confirmation|get back to you|come back to you|check with|find out|look into'
        . '|have (?:someone|the team)|not (?:something|on the record|recorded)|do not have|don\'t have)\b/i';

    /** Did this reply defer instead of answering? */
    public function defers(string $reply): bool
    {
        return (bool) preg_match(self::DEFERRAL, $reply);
    }

    /** File the question once, capped so one chat cannot fill the report. */
    public function record(ChatConversation $conversation, string $question, string $reply = ''): void
    {
        if ($conversation->unansweredQuestions()->count() >= AssistantTools::MAX_QUESTIONS_PER_CHAT) {
            return;
        }

        $conversation->unansweredQuestions()->create([
            'topic'       => $this->topicOf($reply . ' ' . $question),
            'question'    => Str::limit($question, 480),
            'property_id' => $conversation->property_id,
        ]);
    }

    /** The reply usually names the subject; the visitor's wording is the fallback. */
    private function topicOf(string $text): string
    {
        foreach (self::TOPICS as $topic => $pattern) {
            if (preg_match($pattern, $text)) {
                return $topic;
            }
        }

        return 'other';
    }
}
