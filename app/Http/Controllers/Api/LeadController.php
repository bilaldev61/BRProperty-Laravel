<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Services\Leads\LeadRecorder;
use App\Services\Leads\PropertyMatcher;
use App\Services\Leads\Requirements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    use ReadsToolInput;

    /**
     * Who is calling - so the agent can greet a returning caller by name and
     * pick up where they left off. `dynamic_variables` is flat strings, ready
     * for Retell's inbound-call variables.
     */
    public function lookup(Request $request, LeadRecorder $leads): JsonResponse
    {
        $phone = $this->toolInput($request)['phone'] ?? $this->callerPhone($request);
        $lead = $leads->find($phone);

        if (! $lead) {
            return response()->json([
                'known'             => false,
                'dynamic_variables' => ['caller_known' => 'no', 'caller_name' => '', 'caller_last_interest' => ''],
            ]);
        }

        $r = Requirements::stated($lead->requirements ?? []);
        $interest = $lead->property?->title
            ?? collect([implode('/', $r['property_types'] ?? []), implode(', ', $r['areas'] ?? [])])->filter()->implode(' in ');

        $next = $lead->appointments()->upcoming()->first();

        return response()->json([
            'known' => true,
            'lead'  => [
                'id'                => $lead->id,
                'name'              => $lead->name,
                'requirements'      => $r,
                'grade'             => $lead->priority,
                'last_contact'      => $lead->updated_at->toIso8601String(),
                'next_appointment'  => $next?->whenLabel(),
            ],
            'dynamic_variables' => [
                'caller_known'         => 'yes',
                'caller_name'          => $lead->name === LeadRecorder::UNKNOWN_NAME ? '' : $lead->name,
                'caller_last_interest' => (string) $interest,
                'caller_next_viewing'  => (string) $next?->whenLabel(),
            ],
        ]);
    }

    public function matches(Enquiry $enquiry, PropertyMatcher $matcher): JsonResponse
    {
        return response()->json($matcher->match($enquiry->requirements ?? []));
    }
}
