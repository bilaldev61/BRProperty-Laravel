<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Leads\CallIngest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where n8n forwards the voice agent's call webhooks. Send the body exactly as
 * Retell delivered it; the analysed call (call_analyzed) is what qualifies the
 * lead and comes back with the next actions.
 */
class CallController extends Controller
{
    public function store(Request $request, CallIngest $ingest): JsonResponse
    {
        $call = is_array($request->input('call')) ? $request->input('call') : $request->all();

        if (blank($call['call_id'] ?? null)) {
            return response()->json(['message' => 'The call payload needs a call_id.'], 422);
        }

        return response()->json($ingest->ingest($request->all()));
    }
}
