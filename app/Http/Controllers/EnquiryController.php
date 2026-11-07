<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnquiryRequest;
use App\Jobs\SendEnquiryConfirmation;
use App\Models\Enquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class EnquiryController extends Controller
{
    public function store(StoreEnquiryRequest $request): JsonResponse|RedirectResponse
    {
        $enquiry = Enquiry::create([
            'type'        => $request->input('type'),
            'property_id' => $request->input('property_id'),
            'name'        => $request->input('name'),
            'email'       => $request->input('email'),
            'phone'       => $request->input('phone'),
            'message'     => $request->input('message'),
            'details'     => $request->appraisalDetails(),
        ]);

        // Sent after this response goes out - the visitor never waits on mail.
        SendEnquiryConfirmation::for($enquiry);

        $confirmation = match ($enquiry->type) {
            'appraisal' => "Thanks {$enquiry->name}. I'll be in touch within one business day to book your free appraisal.",
            'property'  => "Thanks {$enquiry->name}. Your enquiry is with me now - expect a call or email shortly.",
            default     => "Thanks {$enquiry->name}. Your message has been received and I'll reply shortly.",
        };

        if ($request->expectsJson()) {
            return response()->json(['message' => $confirmation], 201);
        }

        return back()->with('status', $confirmation);
    }
}
