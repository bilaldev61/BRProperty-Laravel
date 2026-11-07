<?php

namespace App\Services;

use App\Models\Enquiry;
use App\Models\Property;
use App\Support\Ai\AiConnector;
use App\Support\Ai\AiUnavailable;
use App\Support\PropertyFacts;

/**
 * Writes a first draft of the agent's reply to an enquiry.
 *
 * The draft is grounded: every fact the model is allowed to state is assembled
 * here from the database and handed over in the prompt. It is told plainly that
 * anything not in that list is unknown and must be deferred to the agent -
 * which is what stops it inventing a price, a possession date or a transfer
 * status. In this market those three are exactly the claims that cause trouble.
 *
 * Output is a draft. It goes into a box the agent edits and sends themselves.
 */
class EnquiryReplyDrafter
{
    public function __construct(private readonly AiConnector $ai) {}

    public function isAvailable(): bool
    {
        return $this->ai->isConfigured();
    }

    /** @throws AiUnavailable */
    public function draftFor(Enquiry $enquiry): string
    {
        $draft = $this->ai->complete($this->system(), $this->prompt($enquiry));

        return trim($draft);
    }

    private function system(): string
    {
        $agent  = config('agent.name');
        $agency = config('agent.agency');
        $phone  = config('agent.phone');

        return <<<PROMPT
        You draft replies for {$agency}, a real estate agency in Dubai, United Arab Emirates.
        You are writing on behalf of the agency, using 'we' and 'our' (never 'I').

        Absolute rules:
        - Use ONLY the facts given in the FACTS section. They come from the agency's own records.
        - If the person asks something the FACTS do not answer - price negotiability, possession
          dates, service charges, DEWA bills, taxes, title deed or NOC status, legal history, anything
          about the paperwork - do NOT guess and do NOT reassure. Say plainly that you will confirm
          and come back to them. Getting this wrong costs a client, so silence beats a guess.
        - Never invent a figure, a date, a measurement or a name.
        - Never promise a viewing time, a discount, or an outcome.

        Style:
        - Plain, direct, warm but businesslike. Write the way a busy agency actually writes.
        - Three short paragraphs at most. No greeting longer than one line.
        - Answer the question that was actually asked, first.
        - Offer a call or a showing at the end where it fits naturally. The number is {$phone}.
        - Plain text only. No markdown, no bullet points, no subject line, no placeholder
          brackets like [name] - use the real details you were given.
        - Sign off with the agency name {$agency}.

        Return only the body of the reply. Nothing else.
        PROMPT;
    }

    private function prompt(Enquiry $enquiry): string
    {
        $facts = $this->facts($enquiry);
        $message = trim((string) $enquiry->message) ?: '(They left no message.)';
        $chat = $this->chat($enquiry);
        $sent = filled($enquiry->auto_reply)
            ? "\nTHE AUTOMATIC REPLY THEY ALREADY RECEIVED FROM US\n(Do not repeat it or contradict it - follow on from it.)\n{$enquiry->auto_reply}\n"
            : '';

        return <<<PROMPT
        FACTS
        {$facts}

        THEIR MESSAGE
        "{$message}"
        {$chat}{$sent}
        Draft our reply to {$enquiry->name}.
        PROMPT;
    }

    /**
     * Leads from the website assistant arrive with the chat behind them. The
     * message above is the assistant's one-line summary; the transcript is what
     * the reply should actually pick up from - especially what it deferred.
     */
    private function chat(Enquiry $enquiry): string
    {
        $conversation = $enquiry->conversation;

        if (! $conversation || $conversation->messages->isEmpty()) {
            return '';
        }

        return <<<CHAT

        THEIR CHAT WITH MY WEBSITE ASSISTANT
        (The message above is the assistant's summary of this chat. The assistant answered only from
        my listing records and told them I would confirm anything else - pick up those open points.
        It is not a source of new facts.)
        {$conversation->transcript()}

        CHAT;
    }

    /** Everything the model is permitted to treat as true, and nothing else. */
    private function facts(Enquiry $enquiry): string
    {
        $lines = [
            'Enquiry type: ' . $enquiry->typeLabel(),
            'Their name: ' . $enquiry->name,
            'Received: ' . $enquiry->created_at->format('j F Y'),
        ];

        if (filled($enquiry->phone)) {
            $lines[] = 'They left a phone number, so a call back is possible.';
        }

        if ($enquiry->property) {
            $lines = array_merge($lines, $this->propertyFacts($enquiry->property));
        }

        if ($enquiry->type === 'appraisal') {
            $lines = array_merge($lines, $this->appraisalFacts($enquiry));
        }

        if (! $enquiry->property && $enquiry->type !== 'appraisal') {
            $lines[] = 'No specific property is attached to this enquiry.';
            $lines[] = 'Areas covered: ' . implode(', ', config('agent.service_areas', []));
        }

        return '- ' . implode("\n- ", $lines);
    }

    /** @return list<string> */
    private function propertyFacts(Property $property): array
    {
        return ['They are asking about this listing: ' . $property->title, ...PropertyFacts::lines($property)];
    }

    /** @return list<string> */
    private function appraisalFacts(Enquiry $enquiry): array
    {
        $lines = ['They are asking what their own property is worth - this is a seller, not a buyer.'];

        $labels = [
            'address'       => 'Their property address',
            'suburb'        => 'Their area',
            'property_type' => 'Their property type',
            'bedrooms'      => 'Their bedrooms',
            'timeframe'     => 'When they want to sell',
        ];

        foreach ($labels as $key => $label) {
            if ($value = $enquiry->detail($key)) {
                $lines[] = "{$label}: {$value}";
            }
        }

        $lines[] = 'A valuation needs an actual visit - do not quote any number in this reply.';

        return $lines;
    }
}
