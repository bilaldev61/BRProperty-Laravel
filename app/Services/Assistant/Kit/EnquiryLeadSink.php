<?php

namespace App\Services\Assistant\Kit;

use App\Jobs\SendEnquiryConfirmation;
use App\Models\ChatConversation;
use App\Models\Enquiry;
use AssistantKit\Contracts\LeadSink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A captured name and email becomes the same enquiry the site always created:
 * same source, same listing link, same confirmation mail, same bell.
 */
class EnquiryLeadSink implements LeadSink
{
    public function __construct(private readonly ChatConversation $conversation) {}

    public function capture(array $lead): string
    {
        $enquiry = DB::transaction(function () use ($lead) {
            $enquiry = Enquiry::create([
                'type'        => $this->conversation->property_id ? 'property' : 'contact',
                'source'      => Enquiry::SOURCE_CHAT,
                'property_id' => $this->conversation->property_id,
                'name'        => $lead['name'] ?: ($lead['email'] ?: 'Chat visitor'),
                'email'       => filled($lead['email']) ? $lead['email'] : null,
                'phone'       => $lead['phone'],
                'message'     => $this->summary($lead['message']),
            ]);

            $this->conversation->update(['enquiry_id' => $enquiry->id]);

            return $enquiry;
        });

        SendEnquiryConfirmation::for($enquiry);

        return (string) $enquiry->id;
    }

    private function summary(?string $asked): string
    {
        return filled($asked)
            ? 'Asked in the assistant chat: ' . Str::limit($asked, 400)
            : 'Left contact details in the assistant chat.';
    }
}
