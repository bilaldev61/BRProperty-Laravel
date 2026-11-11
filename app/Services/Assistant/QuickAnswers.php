<?php

namespace App\Services\Assistant;

use App\Models\ChatConversation;
use App\Models\ChatUnansweredQuestion;
use App\Models\Property;
use Illuminate\Support\Str;

/**
 * Answers from the records what a regex can answer exactly; everything needing
 * judgement goes to the model. A message is answered only when every part of it
 * is recognised, so a rule never half-answers a two-part question.
 */
class QuickAnswers
{
    /** Longer than this is a conversation, not a lookup. */
    private const MAX_LENGTH = 140;

    /**
     * A hello however it gets typed on a phone: vowels dropped ("hlo", "slm"),
     * doubled letters ("hii"), one word short of "assalamualaikum".
     */
    private const HELLO = '(?:hi+|h[ae]l+o+|hl+o*w?|hey+|hy+|hai|sala+m|slm|assalam[u]? ?o? ?alaikum|aoa|yo|good (?:morning|afternoon|evening|day))';

    /**
     * Trailing fillers a visitor types after a hello - polite address or
     * meaningless noise in South-Asian English. A word in FILLER is never
     * a question on its own, so "hlo ge" is still just a hello.
     */
    private const FILLER = '(?:ge|ji|yaar|sahab|sir|madam|bhai|na|haan|bz|bas|bss|to|kya)';

    /**
     * Whole-message small talk, and it stays whole-message: "hi do you have a
     * studio in jvc" is a search and must reach the search. People repeat
     * themselves ("hlo hi"), so the word may come more than once. Trailing
     * fillers after the hello are still small talk: "hlo ge", "hi ji".
     */
    private const GREETING = '/^(?:' . self::HELLO . '(?: +' . self::HELLO . ')*(?: +' . self::FILLER . ')*)$/';

    private const THANKS_WORD = '(?:thanks?( you)?( so much)?|th[nx]|thanx|ty[s]?m?|thank|shukriya|jazak ?allah|bye|goodbye|allah hafiz)';

    // Not a bare "ok": after "shall I pass your details on?" that is an answer.
    private const THANKS = '/^(?:ok(ay)? ' . self::THANKS_WORD . '|' . self::THANKS_WORD . '(?: +' . self::THANKS_WORD . ')*)$/';

    /** "Are you a real person?" / "Who made you?" - a question about the assistant itself. */
    private const IDENTITY = '/\b(?:are you|you(?:\'re| are)?)\s+(?:an?\s+|the\s+)?(?:real|human|person|ai|bot|robot|artificial|chat.?gpt|openai)\b|\b(?:who\s+(?:made|built|created)\s+you|what\s+are\s+you|is\s+this\s+(?:ai|a\s+bot|a\s+robot))\b/i';

    /** Patterns that force the whole message to the model. */
    private const NEEDS_MODEL = [
        '/\d[\d\s-]{6,}\d/',                         // a phone number
        '/\S+@\S+\.\S+/',                            // an email address
        '/\b(call me|contact me|my (name|number)|mera (naam|number))\b/',
        '/\b(visit|viewing|showing|see (it|the house|the home|the property)|tour (the|it)|dekhna|dekh sakt|meet|appointment)\b/',
        '/\b(offer|book|token|buy it|purchase)\b/',
        '/\b(compare|better|best|recommend|suggest|similar|cheaper|other)\b/',
        // Worth-later questions have no answer on the record. "Outlook" especially:
        // it used to be read as the view, so "what is the market outlook" was
        // answered with "the view is Atlantis".
        '/\b(invest|investing|investment|roi|resale|appreciation|capital gain|rental yield|market (?:value|price|trend|outlook)|outlook)\b/',
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
        'financing'         => '/\b(installments?|instal+ments?|qist|loan|mortgage|pre-?approval|financing|payment plan)\b/',
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
        'price'         => '/\b(price|cost|how much|kitne ka|kitne ki|qeemat|qimat|demand|rate)\b/',
        'size'          => '/\b(size|how big|marla|kanal|sq ?ft|square (feet|foot)|covered area|plot area|land area|kitna bara)\b/',
        'rooms'         => '/\b(bed(room)?s?|bath(room)?s?|rooms?|kamr[ae]y?|parking|car spaces?|garage)\b/',
        'status'        => '/\b(available|still for sale|for sale|sold|status|bik gaya|mil sakta)\b/',
        'location'      => '/\b(address|location|located|where is (it|this|the house)|kahan hai|kis jagah)\b/',
        'features'      => '/\b(features|amenities|facilities)\b/',
        'times'         => '/\b(viewings?|inspection|open house|dekhne ka time)\b/',
        'view'          => '/\b(view|views|facing)\b/',
        'furnishing'    => '/\b(furnished|unfurnished|semi furnished|furnishing)\b/',
        'completion'    => '/\b(ready|off ?plan|completion date)\b/',
        'permit'        => '/\b(permit|dld|rera|tra ?khees|registered)\b/',
    ];

    /**
     * Specific landmark kinds are matched before the catch-all, which is dropped
     * when both fire - otherwise "metro nearby" reads as two questions.
     */
    private const NEARBY = [
        'nearby_metro'    => '/\b(metro|monorail|train|public transport)\b/',
        'nearby_mall'     => '/\b(malls?|shopping (centre|center))\b/',
        'nearby_school'   => '/\b(schools?|nurser(y|ies))\b/',
        'nearby_hospital' => '/\b(hospitals?|clinics?|medical cent)\w*/',
        'nearby_beach'    => '/\b(beaches?|waterfront)\b/',
        'nearby_park'     => '/\b(parks?|open space)\b/',
        'nearby_golf'     => '/\b(golf)\b/',
        'nearby_airport'  => '/\b(airports?)\b/',
        'nearby_all'      => '/\b(nearby|near (to )?(here|it|this)|close to|walking distance|whats (around|close)|surrounding)\b/',
    ];

    /** About the agency itself - true on any page. */
    private const AGENCY = [
        'contact' => '/\b(phone number|contact number|your number|whatsapp|how (do|can) i contact|contact (you|details))\b/',
        'hours'   => '/\b(timings?|(?<!\d )h[ou]*rs*|what time do you open|when are you open|are you open|kab khul|kitne baje)\b/',
        'office'  => '/\b(your office(?! (hours|timings?))|office address|office kahan|where are you based)\b/',
        'areas'   => '/\b((which|what) areas|areas (do )?you (cover|deal|work)|kin areas|kahan kahan)\b/',
    ];

    /** Words that make a message about property - off-topic needs none of them. */
    private const PROPERTY_WORDS = '/\b(house|villa|home|ghar|plot|land|flat|apartment|townhouse|property|properties|penthouse|studio|marla|kanal|crore|lakh|aed|dirham|dubai|marina|jvc|jlt|palm|downtown|business bay|rent|buy|sell|listing|area|community|service charge|price|bed|room)\w*/';

    private const OFF_TOPIC = '/\b(weather|recipe|cricket|football|movie|film|song|joke|poem|story|code|coding|python|javascript|php|homework|essay|assignment|maths?|translate|politics|election|news|chatgpt|openai|write me)\b/';

    /**
     * A hello or a thanks on its own - small talk rather than a question.
     *
     * Used by the repeat guard: a visitor saying hello twice is not stuck on an
     * answer, so their second hello still gets a hello back.
     */
    public function isSmallTalk(string $message): bool
    {
        $text = $this->normalise($message);

        return (bool) preg_match(self::GREETING, $text) || (bool) preg_match(self::THANKS, $text);
    }

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

        if (preg_match(self::IDENTITY, $message)) {
            return $this->identity();
        }

        if (preg_match(self::OFF_TOPIC, $text) && ! preg_match(self::PROPERTY_WORDS, $text)) {
            return $this->reply('I can only help with property questions - our listings, areas, prices and arranging a call with ' . config('agent.name') . '. What are you looking for?', 'off_topic');
        }

        if (mb_strlen($text) > self::MAX_LENGTH) {
            return null;
        }

        foreach (self::NEEDS_MODEL as $pattern) {
            if (preg_match($pattern, $text)) {
                return null;
            }
        }

        $property = $this->listingInFocus($conversation);

        // Compound messages are answered together only when every part stands alone.
        if (($joined = $this->answerEachClause($conversation, $property, $message)) !== null) {
            return $joined;
        }

        $intents = $this->intents($text, $property);

        // Exactly one thing asked, or it is the model's job.
        if (count($intents) !== 1) {
            return null;
        }

        return $this->resolve($conversation, $intents[0], $property, $message);
    }

    /**
     * Each clause answered in turn, or null when any part is unanswerable.
     */
    private function answerEachClause(ChatConversation $conversation, ?Property $property, string $message): ?array
    {
        $clauses = $this->clauses($message);

        if (count($clauses) < 2) {
            return null;
        }

        $intents = [];

        foreach ($clauses as $clause) {
            $found = $this->intents($this->normalise($clause), $property);

            if (count($found) !== 1) {
                return null;
            }

            $intents[] = $found[0];
        }

        $intents = array_values(array_unique($intents, SORT_REGULAR));

        if (count($intents) < 2) {
            return null; // the same thing asked twice - one answer is enough
        }

        $answers = [];

        foreach ($intents as $intent) {
            $answer = $this->resolve($conversation, $intent, $property, $message);

            if ($answer === null) {
                return null; // one part unanswerable: hand the whole message on
            }

            $answers[] = $answer;
        }

        return $this->join($answers);
    }

    /**
     * Split where a new question starts - a question mark or an "and".
     *
     * @return list<string>
     */
    private function clauses(string $message): array
    {
        $parts = preg_split('/[?;]+|\s+(?:and|&|plus|also)\s+/iu', $message, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = array_values(array_filter(array_map(fn($part) => trim($part), $parts)));

        return count($parts) > 3 ? [] : $parts;
    }

    /** @param  list<array{text: string, kind: string}>  $answers */
    private function join(array $answers): array
    {
        if (count($answers) === 1) {
            return $answers[0];
        }

        // The price answer already ends with "...room on it", which the
        // negotiation reply repeats. Say it once.
        $labels = array_map(fn($answer) => preg_replace('/^(listing|deferral|agency)_/', '', $answer['kind']), $answers);

        if (in_array('price_negotiation', $labels, true)) {
            $tail = '/\s*' . preg_quote((string) config('agent.name'), '/') . ' can confirm whether there is any room on it\./u';

            foreach ($answers as $index => $answer) {
                if ($answer['kind'] === 'listing_price') {
                    $answers[$index]['text'] = trim((string) preg_replace($tail, '', $answer['text']));
                }
            }
        }

        // answered_by is 40 characters, so the joined label has to stay short.
        $kind = 'multi_' . implode('+', $labels);

        return $this->reply(
            implode("\n\n", array_column($answers, 'text')),
            mb_strlen($kind) > 34 ? 'multi' : $kind,
        );
    }

    /** @param  array{0: string, 1: string}  $intent */
    private function resolve(ChatConversation $conversation, array $intent, ?Property $property, string $message): ?array
    {
        [$group, $name] = $intent;

        return match ($group) {
            'deferral' => $this->defer($conversation, $name, $message),
            'listing'  => $property === null ? null : $this->listingFact($property, $name),
            'agency'   => $this->agencyFact($name),
        };
    }

    /** @return list<array{0: string, 1: string}> */
    private function intents(string $text, ?Property $property): array
    {
        $onListing = $property !== null;
        $found = [];

        foreach (self::DEFERRALS as $topic => $pattern) {
            if (! preg_match($pattern, $text)) {
                continue;
            }

            // A recorded fact beats a deferral - quote it rather than promise a call.
            if ($topic === 'charges' && $property?->service_charge) {
                $found[] = ['listing', 'service_charge'];
                continue;
            }

            if ($topic === 'possession' && filled($property?->handover)) {
                $found[] = ['listing', 'handover'];
                continue;
            }

            $found[] = ['deferral', $topic];
        }

        foreach (self::AGENCY as $intent => $pattern) {
            if (preg_match($pattern, $text)) {
                $found[] = ['agency', $intent];
            }
        }

        // "Is the price final?" is about negotiation, not the price itself.
        $negotiating = in_array(['deferral', 'price_negotiation'], $found, true);

        // A listing fact needs that listing page; elsewhere "how much?" needs the model.
        if ($onListing) {
            foreach (self::LISTING_FACTS as $intent => $pattern) {
                if (preg_match($pattern, $text) && ! ($intent === 'price' && $negotiating)) {
                    $found[] = ['listing', $intent];
                }
            }

            $nearby = [];

            foreach (self::NEARBY as $intent => $pattern) {
                if (preg_match($pattern, $text)) {
                    $nearby[] = $intent;
                }
            }

            if (count($nearby) > 1) {
                $nearby = array_values(array_filter($nearby, fn($intent) => $intent !== 'nearby_all'));
            }

            foreach ($nearby as $intent) {
                $found[] = ['listing', $intent];
            }
        } elseif ($this->matchesAny(self::LISTING_FACTS, $text) || $this->matchesAny(self::NEARBY, $text)) {
            return []; // forces the model
        }

        return $found;
    }

    /**
     * The listing a bare "how big is it?" refers to - ambiguous once the model has
     * pointed at another one, so it then goes to the model.
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
        if (str_starts_with($intent, 'nearby_')) {
            $nearby = $this->nearby($property, $intent);

            return $nearby === null ? null : $this->reply($nearby, 'listing_' . $intent);
        }

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
            'view'          => filled($property->view_type) ? 'The view is ' . $property->view_type . '.' : null,
            'furnishing'    => $property->furnishingLabel() ? 'It is ' . Str::lower($property->furnishingLabel()) . '.' : null,
            'completion'    => $property->completionLabel()
                ? 'Completion: ' . $property->completionLabel() . (filled($property->handover) ? ', handover ' . $property->handover : '') . '.'
                : null,
            'handover'      => filled($property->handover) ? 'Handover is ' . $property->handover . '.' : null,
            'service_charge' => $property->serviceChargeDisplay()
                ? 'The service charge is ' . $property->serviceChargeDisplay() . '.'
                . (filled($property->payment_terms) ? ' Payment terms as advertised: ' . $property->payment_terms . '.' : '')
                : null,
            'permit'        => filled($property->permit_number)
                ? 'The advertising permit number is ' . $property->permit_number . '.'
                : null,
        };

        // Nothing recorded for it: the model can say so and offer a call back.
        return $text === null ? null : $this->reply($text, 'listing_' . $intent);
    }

    /**
     * Landmarks on the record; a missing kind is said out loud - never guessed.
     */
    private function nearby(Property $property, string $intent): ?string
    {
        $type = Str::after($intent, 'nearby_');
        $groups = $property->nearbyByType();

        if ($type !== 'all') {
            if (empty($groups[$type])) {
                return 'That is not recorded for this listing. ' . config('agent.name')
                    . ' can confirm it - shall I pass your name and email on?';
            }

            $groups = [$type => $groups[$type]];
        }

        if (! $groups) {
            return null;
        }

        $lines = [];

        foreach ($groups as $kind => $places) {
            $lines[] = $property->nearbyTypeLabel($kind);

            foreach ($places as $place) {
                $lines[] = '- ' . $place['name'] . (filled($place['distance'] ?? null) ? ' - ' . $place['distance'] : '');
            }
        }

        return ($type === 'all' ? 'What is near the property:' : 'Nearby:') . "\n" . implode("\n", $lines);
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
     * Something only the agent can answer - logged so the question is not lost.
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
        $agent = config('agent.name');

        // Once the details are in, asking for them again reads as a bot that
        // forgot the conversation it is in the middle of.
        $ask = $conversation->isLead()
            ? ' ' . $agent . ' has your details and will be in touch.'
            : ' Leave your name and email here and ' . $agent . ' will come back to you.';

        return $this->reply(
            $agent . ' will confirm ' . self::DEFERRAL_PHRASES[$topic] . $about . ' - I do not want to guess on that.' . $ask,
            'deferral_' . $topic,
        );
    }

    private function greeting(?Property $property): string
    {
        return $property
            ? 'Hi! Ask me anything about ' . $property->title . ' - price, size, features, or arranging a visit.'
            : 'Hi! Tell me the area, budget and type of property you are after, and I will check our listings.';
    }

    private function identity(): array
    {
        $agent  = config('agent.name');
        $agency = config('agent.agency');

        return $this->reply(
            "I'm the website assistant for {$agency} - an AI tool that answers from our listing records. " .
                'For anything that needs a human, ' . $agent . ' and the team handle the real side.',
            'identity',
        );
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
