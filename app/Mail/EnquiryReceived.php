<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The first email to the person who made an enquiry.
 *
 * Two forms. With a `$reply`, the body is an AI answer to their question that
 * has already passed ReplyCheck, and a footnote says it was prepared
 * automatically. Without one, it is the template confirmation, built entirely
 * from the records. The listing card, contact details and office hours are
 * always from the records, never from the model. Neither form repeats the
 * visitor's own text - anyone can type any address into a public form.
 *
 * It is sent in the agent's name, and replies go to the agent's own inbox.
 */
class EnquiryReceived extends Mailable
{
    /**
     * @param  string|null  $reply  an AI answer that passed the fact check; null sends the template wording
     */
    public function __construct(
        public readonly Enquiry $enquiry,
        public readonly ?string $reply = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('agent.agency')),
            replyTo: [new Address(config('agent.email'), config('agent.name'))],
            subject: $this->subjectLine(),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.enquiry-received', with: [
            'enquiry'  => $this->enquiry,
            'property' => $this->enquiry->property?->is_published ? $this->enquiry->property : null,
            'agent'    => config('agent'),
            'whatsapp' => 'https://wa.me/' . config('agent.whatsapp'),
            // Blank-line separated, rendered as escaped text: it is model output.
            'paragraphs' => $this->reply ? preg_split('/\n\s*\n/', trim($this->reply)) : [],
        ]);
    }

    private function subjectLine(): string
    {
        return match (true) {
            $this->enquiry->type === 'appraisal'    => 'Your free appraisal request - ' . config('agent.agency'),
            $this->reply !== null && $this->enquiry->property !== null => 'Re: your enquiry about ' . $this->enquiry->property->title,
            $this->reply !== null                   => 'Re: your enquiry - ' . config('agent.agency'),
            $this->enquiry->property !== null       => 'Your enquiry about ' . $this->enquiry->property->title,
            default                                 => 'Thanks for getting in touch - ' . config('agent.agency'),
        };
    }
}
