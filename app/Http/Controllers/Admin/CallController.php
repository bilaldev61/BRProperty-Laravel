<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Call;

/** Every call the phone agent handled, and what came of it. */
class CallController extends Controller
{
    public function index()
    {
        $since = now()->subDays(30);

        return view('admin.calls.index', [
            'calls' => Call::with('enquiry')->latest('started_at')->latest('id')->paginate(25),
            'stats' => [
                'calls'   => Call::where('created_at', '>=', $since)->count(),
                'leads'   => Call::where('created_at', '>=', $since)->whereNotNull('enquiry_id')->distinct('enquiry_id')->count('enquiry_id'),
                'minutes' => (int) round(Call::where('created_at', '>=', $since)->sum('duration_seconds') / 60),
                'cost'    => Call::where('created_at', '>=', $since)->sum('cost_cents'),
            ],
        ]);
    }

    public function show(Call $call)
    {
        return view('admin.calls.show', ['call' => $call->load('enquiry')]);
    }
}
