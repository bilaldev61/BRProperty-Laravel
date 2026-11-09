<?php

namespace App\Services\Assistant;

use App\Jobs\SendEnquiryConfirmation;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Enquiry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Takes the name and email a visitor leaves after the assistant has run out of
 * answers, and turns them into an enquiry the agent can act on.
 *
 * This is the no-model twin of the save_lead tool: same result from a regex, so
 * a chat that cannot be answered still ends with an email address rather than a
 * phone number the visitor may never ring. The enquiry carries the question the
 * assistant could not answer, so the agent opens it knowing why it exists.
 */
class ContactCapture
{
    /** Deliberately loose: a visitor typing an address is never wrong about it. */
    private const EMAIL = '/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/';

    private const PHONE = '/(\+?\d[\d\s()-]{7,}\d)/';

    /** Only an explicit introduction names the person; "I am looking for" does not. */
    private const NAME = '/\b(?:my name is|this is|name\s*[:\-])\s*([\p{L}][\p{L}\'.-]*(?:\s+[\p{L}][\p{L}\'.-]*){0,2})/iu';

    /**
     * Words that are never a name, so "leave my email, ahmed@example.com" does
     * not read as a person called "Leave My Email".
     */
    private const NAME_NOISE = [
        'a',
        'and',
        'at',
        'email',
        'for',
        'hello',
        'here',
        'hey',
        'hi',
        'is',
        'it',
        'mail',
        'me',
        'my',
        'name',
        'ok',
        'okay',
        'please',
        'reach',
        'sure',
        'thanks',
        'the',
        'this',
        'to',
        'you',
        'your',
    ];

    /** Null unless this message is the visitor leaving contact details. */
    public function capture(ChatConversation $conversation, string $message): ?string
    {
        // One lead per chat: a second address is a correction, not a new client.
        if ($conversation->isLead() || ! preg_match(self::EMAIL, $message, $found)) {
            return null;
        }

        $email = $found[0];
        $name = $this->statedName($message) ?? $this->nameFrom($message, $email);

        $enquiry = DB::transaction(function () use ($conversation, $message, $email, $name) {
            $enquiry = Enquiry::create([
                'type'        => $conversation->property_id ? 'property' : 'contact',
                'source'      => Enquiry::SOURCE_CHAT,
                'property_id' => $conversation->property_id,
                'name'        => $name,
                'email'       => $email,
                'phone'       => $this->phone($message),
                'message'     => $this->summary($conversation),
            ]);

            $conversation->update(['enquiry_id' => $enquiry->id]);

            return $enquiry;
        });

        SendEnquiryConfirmation::for($enquiry);

        return sprintf(
            'Thank you, %s - I have passed this to %s, who will come back to you at %s. Anything else you would like me to check?',
            $name,
            config('agent.name'),
            $email,
        );
    }

    /** A name given as an introduction - "my name is Ahmed", "this is Ahmed". */
    private function statedName(string $message): ?string
    {
        return preg_match(self::NAME, $message, $found)
            ? Str::limit(Str::title(trim($found[1])), 120, '')
            : null;
    }

    /** The name read out of the message, so the agent opens the lead knowing who it is. */
    private function nameFrom(string $message, string $email): string
    {
        // "Ahmed Khan, ahmed.khan@example.com" - what is left once the address
        // and the punctuation are taken out.
        $before = (string) preg_replace('/[^\p{L}\s\'.-]+/u', ' ', Str::before($message, $email));

        $words = collect(preg_split('/\s+/', trim($before)) ?: [])
            ->filter(fn(string $word) => mb_strlen($word) >= 2 && ! in_array(mb_strtolower($word), self::NAME_NOISE, true))
            ->take(3);

        if ($words->count() > 0 && mb_strlen($words->implode(' ')) <= 60) {
            return Str::title($words->implode(' '));
        }

        // "bilal.raza@example.com" reads as Bilal Raza - better than "there".
        return Str::limit(Str::title(str_replace(['.', '_', '-'], ' ', Str::before($email, '@'))), 120, '');
    }

    private function phone(string $message): ?string
    {
        return preg_match(self::PHONE, $message, $found) ? trim($found[1]) : null;
    }

    /**
     * What the agent needs to know, in one line: the question the assistant
     * could not answer, which is why the visitor is here at all.
     */
    private function summary(ChatConversation $conversation): string
    {
        $asked = $conversation->unansweredQuestions()->latest('id')->value('question');

        if (filled($asked)) {
            return 'Asked in the assistant chat: ' . $asked;
        }

        $last = $conversation->messages()
            ->where('role', ChatMessage::USER)
            ->reorder('id', 'desc')
            ->skip(1)
            ->value('content');

        return filled($last)
            ? 'Asked in the assistant chat: ' . Str::limit($last, 400)
            : 'Left contact details in the assistant chat.';
    }
}
