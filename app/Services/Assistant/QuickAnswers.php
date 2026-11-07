<?php

namespace App\Services\Assistant;

use App\Models\ChatConversation;
use App\Models\ChatUnansweredQuestion;
use App\Models\Property;
use Illuminate\Support\Str;

/**
 * Answers the questions that do not need a model, straight from the database.
 *
 * Most chat traffic is the same dozen questions - how much, how big, is it
 * still available, what are your hours - and every one of those is a column
 * we already have. Answering them here is instant, free, and exactly right
 * every time. The model is kept for what actually needs it: searches,
 * comparisons, follow-ups, and taking a visitor's details.
 *
 * The rule is to answer only when sure. A message gets an answer here only if
 * it asks exactly one thing this class recognises and carries nothing that
 * needs judgement - contact details, a request to visit, a second question.
 * Anything else returns null and goes to the model, so a rule can never give a
 * half-answer to a two-part question.
 */
class QuickAnswers
{
    /** Longer than this is a conversation, not a lookup. */
    private const MAX_LENGTH = 140;

    /** Whole-message small talk. */
    private const GREETING = '/^(hi+|hello|hey|salam|salaam|assalam ?o? ?alaikum|aoa|good (morning|afternoon|evening))$/';

    // Not "ok" or "yes" on their own: after "shall I pass your details on?"
    // those are an answer, and the model needs to see it.
    private const THANKS = '/^(ok(ay)? thanks?|thanks?( you)?( so much)?|thx|shukriya|jazak ?allah|bye|goodbye|allah hafiz)$/';

    /**
     * Signals that the model has to handle the message, whatever else it says:
     * contact details (save_lead), visits and offers, and open-ended asks.
     */
    private const NEEDS_MODEL = [
        '/\d[\d\s-]{6,}\d/',                         // a phone number
        '/\S+@\S+\.\S+/',                            // an email address
        '/\b(call me|contact me|my (name|number)|mera (naam|number))\b/',
        '/\b(visit|viewing|showing|see (it|the house|the home|the property)|tour (the|it)|dekhna|dekh sakt|meet|appointment)\b/',
        '/\b(offer|book|token|buy it|purchase)\b/',
        '/\b(compare|better|best|recommend|suggest|similar|cheaper|other)\b/',
        '/\b(why|explain)\b/',
        // A search, not a question about the listing on screen.
        '/\b(houses|homes|plots|flats|condos?|townhomes?|apartments|properties|portions|any|under|below|above|between|budget|crore|lakh|million)\b/',
        '/[$]\s?\d/',                                // a quoted budget ("under $500K")
    ];

    /** Things only the agent can confirm - deferred without asking the model. */
    private const DEFERRALS = [
        'price_negotiation' => '/\b(negotiable|negotiate|final price|price final|last price|lowest price|discount|kam ho|kam kar)\b/',
        'possession'        => '/\b(possession|handover|hand over|closing date|when (can|do) (i|we) (get|move)|qabza)\b/',
        'paperwork'         => '/\b(noc|transfer|registry|mutation|fard|oqood|ejari|documents?|paperwork|file clear|clear title|title (commitment|search|issue|deed)|survey|escrow)\b/',
        'charges'           => '/\b(dues|maintenance charges?|service charges?|taxe?s?|transfer fee|hoa|association fee|charges)\b/',
        'financing'         => '/\b(installments?|qist|loan|mortgage|pre-?approval|financing|payment plan)\b/',
    ];

    /** What the deferral reply says the agent will confirm. */
    private const DEFERRAL_PHRASES = [
        'price_negotiation' => 'whether there is room on the price',
        'possession'        => 'the closing and possession details',
        'paperwork'         => 'the title and paperwork status',
        'charges'           => 'the service charges, DEWA and fees',
        'financing'         => 'the payment and financing options',
    ];

    /** Facts about the listing on screen, answered from its record. */
    private const LISTING_FACTS = [
        'price'    => '/\b(price|cost|how much|kitne ka|kitne ki|qeemat|qimat|demand|rate)\b/',
        'size'     => '/\b(size|how big|marla|kanal|sq ?ft|square (feet|foot)|covered area|plot area|land area|kitna bara)\b/',
        'rooms'    => '/\b(bed(room)?s?|bath(room)?s?|rooms?|kamr[ae]y?|parking|car spaces?|garage)\b/',
        'status'   => '/\b(available|still for sale|for sale|sold|status|bik gaya|mil sakta)\b/',
        'location' => '/\b(address|location|located|where is (it|this|the house)|kahan hai|kis jagah)\b/',
        'features' => '/\b(features|amenities|facilities)\b/',
        'times'    => '/\b(viewings?|inspection|open house|dekhne ka time)\b/',
    ];

    /** About the agency itself - true on any page. */
    private const AGENCY = [
        'contact' => '/\b(phone number|contact number|your number|whatsapp|how (do|can) i contact|contact (you|details))\b/',
        'hours'   => '/\b(timings?|opening hours|office hours|when are you open|are you open|kab khul)\b/',
        'office'  => '/\b(your office(?! (hours|timings?))|office address|office kahan|where are you based)\b/',
        'areas'   => '/\b((which|what) areas|areas (do )?you (cover|deal|work)|kin areas|kahan kahan)\b/',
    ];

    /** Words that make a message about property - off-topic needs none of them. */
    private const PROPERTY_WORDS = '/\b(house|villa|home|ghar|plot|land|flat|apartment|townhouse|property|properties|penthouse|studio|marla|kanal|crore|lakh|aed|dirham|dubai|marina|jvc|jlt|palm|downtown|business bay|rent|buy|sell|listing|area|community|service charge|price|bed|room)\w*/';

    private const OFF_TOPIC = '/\b(weather|recipe|cricket|football|movie|film|song|joke|poem|story|code|coding|python|javascript|php|homework|essay|assignment|maths?|translate|politics|election|news|chatgpt|openai|who (made|built|created) you|write me)\b/';

    /** @return array{text: string, kind: string}|null */
    public function answer(ChatConversation $conversation, string $message): ?array
    {
        if (! config('ai.chat.quick_answers')) {
            return null;
        }

        $text = $this->normalise($message);

        if (preg_match(self::GREETING, $text)) {
            return $this->reply($this->greeting($conversation->property), 'greeting');
        }

        if (preg_match(self::THANKS, $text)) {
            return $this->reply("You're welcome. Ask me anything else about our properties, or call " . config('agent.name') . ' on ' . config('agent.phone') . '.', 'thanks');
        }

        if (preg_match(self::OFF_TOPIC, $text) && ! preg_match(self::PROPERTY_WORDS, $text)) {
            return $this->reply('I can only help with property questions - our listings, areas, prices and arranging a call with ' . config('agent.name') . '. What are you looking for?', 'off_topic');
        }

        if (mb_strlen($text) > self::MAX_LENGTH || substr_count($message, '?') > 1) {
            return null;
        }

        foreach (self::NEEDS_MODEL as $pattern) {
            if (preg_match($pattern, $text)) {
                return null;
            }
        }

        $property = $this->listingInFocus($conversation);
        $intents = $this->intents($text, $property !== null);

        // Exactly one thing asked, or it is the model's job.
        if (count($intents) !== 1) {
            return null;
        }

        [$group, $intent] = $intents[0];

        return match ($group) {
            'deferral' => $this->defer($conversation, $intent, $message),
            'listing'  => $this->listingFact($property, $intent),
            'agency'   => $this->agencyFact($intent),
        };
    }

    /** @return list<array{0: string, 1: string}> */
    private function intents(string $text, bool $onListing): array
    {
        $found = [];

        foreach (self::DEFERRALS as $topic => $pattern) {
            if (preg_match($pattern, $text)) {
                $found[] = ['deferral', $topic];
            }
        }

        foreach (self::AGENCY as $intent => $pattern) {
            if (preg_match($pattern, $text)) {
                $found[] = ['agency', $intent];
            }
        }

        // "Is the price final?" is about negotiation, not the price itself.
        $negotiating = in_array(['deferral', 'price_negotiation'], $found, true);

        // A listing fact only means something on that listing's page; elsewhere
        // "how much?" needs the model to work out which property is meant.
        if ($onListing) {
            foreach (self::LISTING_FACTS as $intent => $pattern) {
                if (preg_match($pattern, $text) && ! ($intent === 'price' && $negotiating)) {
                    $found[] = ['listing', $intent];
                }
            }
        } elseif ($this->matchesAny(self::LISTING_FACTS, $text)) {
            return []; // forces the model
        }

        return $found;
    }

    /**
     * The listing a bare "how big is it?" refers to - the page's own, unless the
     * model has since pointed the visitor at another one. Then "it" is
     * ambiguous and the model, which can read the chat, has to answer.
     */
    private function listingInFocus(ChatConversation $conversation): ?Property
    {
        $property = $conversation->property;

        if (! $property?->is_published) {
            return null;
        }

        $lastReply = $conversation->messages()
            ->where('role', 'assistant')
            ->reorder('id', 'desc')
            ->value('content');

        $pointedElsewhere = $lastReply
            && str_contains($lastReply, '/properties/')
            && ! str_contains($lastReply, '/properties/' . $property->slug);

        return $pointedElsewhere ? null : $property;
    }

    private function listingFact(Property $property, string $intent): ?array
    {
        $text = match ($intent) {
            'price'    => $this->price($property),
            'size'     => $this->size($property),
            'rooms'    => $this->rooms($property),
            'status'   => $this->status($property),
            'location' => 'It is at ' . $property->shortAddress() . ', ' . $property->state . '. The map on this page shows the approximate location.',
            'features' => filled($property->features)
                ? "Listed features:\n- " . implode("\n- ", (array) $property->features)
                : null,
            'times'    => filled($property->inspection_times) && $property->status !== 'sold'
                ? 'Advertised viewing times: ' . $property->inspection_times . '. Want me to pass your details to ' . config('agent.name') . ' to arrange a time?'
                : null,
        };

        // Nothing recorded for it: the model can say so and offer a call back.
        return $text === null ? null : $this->reply($text, 'listing_' . $intent);
    }

    private function price(Property $property): string
    {
        if ($property->status === 'sold') {
            return $property->priceDisplay() . '.';
        }

        return 'The advertised price is ' . $property->priceDisplay() . '. '
            . config('agent.name') . ' can confirm whether there is any room on it.';
    }

    private function size(Property $property): ?string
    {
        $parts = array_filter([
            $property->landDisplay() ? 'a land size of ' . $property->landDisplay() : null,
            $property->floorDisplay() ? 'a covered area of ' . $property->floorDisplay() : null,
        ]);

        return $parts ? 'It has ' . implode(' and ', $parts) . '.' : null;
    }

    private function rooms(Property $property): ?string
    {
        if (! $property->hasRooms()) {
            return $property->type === 'land' ? 'This is a plot, so there are no rooms yet.' : null;
        }

        return sprintf(
            'It has %d bedrooms, %d bathrooms and parking for %d %s.',
            $property->bedrooms,
            $property->bathrooms,
            $property->carspaces,
            Str::plural('car', $property->carspaces),
        );
    }

    private function status(Property $property): string
    {
        return match ($property->status) {
            'for_sale'    => 'Yes, it is currently for sale at ' . $property->priceDisplay() . '.',
            'under_offer' => 'It is currently under offer. ' . config('agent.name') . ' can tell you whether it is still worth putting your name down.',
            'sold'        => 'This one has sold. Ask me about similar properties that are still available.',
            default       => 'Its current status is ' . $property->statusLabel() . '.',
        };
    }

    private function agencyFact(string $intent): array
    {
        $agent = config('agent.name');

        $text = match ($intent) {
            'contact' => "You can call or WhatsApp {$agent} on " . config('agent.phone') . ', or email ' . config('agent.email') . '.',
            'hours'   => "Office hours:\n" . collect(config('agent.hours'))->map(fn($time, $days) => "- {$days}: {$time}")->implode("\n"),
            'office'  => 'The office is at ' . config('agent.office.street') . ', ' . config('agent.office.suburb') . '. Call ' . config('agent.phone') . ' before visiting.',
            'areas'   => "{$agent} covers " . implode(', ', config('agent.service_areas')) . '. Tell me the area and budget you have in mind and I will check what is available.',
        };

        return $this->reply($text, 'agency_' . $intent);
    }

    /**
     * Something only the agent can answer. Saying so needs no model - and
     * logging it here is more reliable than asking a model to remember to.
     */
    private function defer(ChatConversation $conversation, string $topic, string $message): array
    {
        if ($conversation->unansweredQuestions()->count() < AssistantTools::MAX_QUESTIONS_PER_CHAT) {
            $conversation->unansweredQuestions()->create([
                'topic'       => $topic,
                'question'    => Str::limit($message, 480),
                'property_id' => $conversation->property_id,
            ]);
        }

        $about = $conversation->property ? ' for this property' : '';

        return $this->reply(
            config('agent.name') . ' will confirm ' . self::DEFERRAL_PHRASES[$topic] . $about . ' - I do not want to guess on that. '
                . 'If you share your name and phone number, I will pass them on for a call back.',
            'deferral_' . $topic,
        );
    }

    private function greeting(?Property $property): string
    {
        return $property
            ? 'Hi! Ask me anything about ' . $property->title . ' - price, size, features, or arranging a visit.'
            : 'Hi! Tell me the area, budget and type of property you are after, and I will check our listings.';
    }

    private function matchesAny(array $patterns, string $text): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function normalise(string $message): string
    {
        $text = mb_strtolower(trim($message));
        $text = preg_replace('/[^\p{L}\p{N}@.\s-]+/u', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', rtrim($text, '. ')));
    }

    /** @return array{text: string, kind: string} */
    private function reply(string $text, string $kind): array
    {
        return ['text' => $text, 'kind' => $kind];
    }
}
