<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendEnquiryConfirmation;
use App\Models\Enquiry;
use App\Services\EnquiryReplyDrafter;
use App\Services\Leads\PropertyMatcher;
use App\Support\Ai\AiUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $enquiries = Enquiry::with('property')
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('priority'), fn ($q, $v) => $q->where('priority', $v))
            ->when($request->boolean('unread'), fn ($q) => $q->unread())
            ->when($request->input('view') === 'needs_reply', fn ($q) => $q->needsReply())
            ->when($request->input('view') === 'overdue', fn ($q) => $q->overdue())
            ->byUrgency()
            ->paginate(20)
            ->withQueryString();

        return view('admin.enquiries.index', [
            'enquiries'       => $enquiries,
            'unreadCount'     => Enquiry::unread()->count(),
            'needsReplyCount' => Enquiry::needsReply()->count(),
            'overdueCount'    => Enquiry::overdue()->count(),
        ]);
    }

    public function show(Enquiry $enquiry, EnquiryReplyDrafter $drafter, PropertyMatcher $matcher)
    {
        $enquiry->forceFill(['read_at' => $enquiry->read_at ?? now()])->save();
        $enquiry->load(['conversation.messages', 'calls', 'appointments.property']);

        return view('admin.enquiries.show', [
            'enquiry'     => $enquiry,
            'aiAvailable' => $drafter->isAvailable(),
            // Live, so a listing added since the call shows up here.
            'matches'     => filled($enquiry->requirements) ? $matcher->match($enquiry->requirements) : null,
        ]);
    }

    /** Move an enquiry along the workflow, or set when to chase it. */
    public function update(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate([
            'status'       => ['required', Rule::in(array_keys(Enquiry::statuses()))],
            'follow_up_at' => ['nullable', 'date'],
        ]);

        // Closing an enquiry settles it - a stale chase date would keep it
        // showing up as overdue work that no longer exists.
        if ($data['status'] === Enquiry::STATUS_CLOSED) {
            $data['follow_up_at'] = null;
        }

        $enquiry->update($data);

        return back()->with('status', 'Enquiry marked '.strtolower($enquiry->statusLabel()).'.');
    }

    /**
     * Draft a reply for the agent to edit. Nothing is sent and nothing is
     * stored - the draft only ever exists in the box on screen.
     */
    public function draft(Enquiry $enquiry, EnquiryReplyDrafter $drafter): JsonResponse
    {
        if (! $drafter->isAvailable()) {
            return response()->json(['message' => 'AI drafting is not configured.'], 503);
        }

        try {
            return response()->json(['draft' => $drafter->draftFor($enquiry->load(['property', 'conversation.messages']))]);
        } catch (AiUnavailable $e) {
            // The agent can still write the reply themselves, so this is a
            // degraded feature, not a broken page.
            report($e);

            return response()->json(['message' => 'Could not draft a reply just now. Please write one below.'], 503);
        }
    }

    /** Send (or resend) the confirmation email now, and say whether it went. */
    public function confirmation(Enquiry $enquiry)
    {
        abort_unless(filled($enquiry->email), 422, 'This enquiry has no email address.');

        if (! SendEnquiryConfirmation::mailIsConfigured()) {
            return back()->with('status', 'Email is not set up on this server yet, so nothing was sent.');
        }

        $sent = (new SendEnquiryConfirmation($enquiry->id, resend: true))->handle();

        return back()->with('status', $sent
            ? "Confirmation email sent to {$enquiry->email}."
            : 'The email could not be sent - check the mail settings, or try again in an hour.');
    }

    public function destroy(Enquiry $enquiry)
    {
        $enquiry->delete();

        return redirect()->route('admin.enquiries.index')->with('status', 'Enquiry deleted.');
    }
}
