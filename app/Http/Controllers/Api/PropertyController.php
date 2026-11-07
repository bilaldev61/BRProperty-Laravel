<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Listings\PropertySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Listing search and details for the phone agent, during the call. */
class PropertyController extends Controller
{
    use ReadsToolInput;

    public function search(Request $request, PropertySearch $search): JsonResponse
    {
        $criteria = Validator::make($this->toolInput($request), PropertySearch::rules())->validate();
        $result = $search->search($criteria, (int) min(10, max(1, (int) $request->input('limit', 5))));

        return response()->json([
            'total_matches' => $result['total'],
            'properties'    => $result['properties'],
            // A sentence the voice agent can say as-is when nothing fits.
            'note'          => $result['total'] === 0 ? 'No listing matches those details right now.' : null,
        ]);
    }

    public function show(Request $request, PropertySearch $search, ?string $slug = null): JsonResponse
    {
        $property = $search->find($slug ?? ($this->toolInput($request)['slug'] ?? null));

        if (! $property) {
            return response()->json(['found' => false, 'message' => 'No published listing has that slug.'], 404);
        }

        return response()->json(['found' => true, 'property' => PropertySearch::details($property)]);
    }
}
