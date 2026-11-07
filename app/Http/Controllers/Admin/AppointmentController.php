<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Viewings booked by the phone agent: confirm them, and record what happened. */
class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $past = $request->input('view') === 'past';

        $appointments = Appointment::with(['enquiry', 'property'])
            ->when($past,
                fn ($q) => $q->where('starts_at', '<', now())->orderByDesc('starts_at'),
                fn ($q) => $q->where('starts_at', '>=', now()->subHours(2))->orderBy('starts_at'))
            ->paginate(25)
            ->withQueryString();

        return view('admin.appointments.index', [
            'appointments' => $appointments,
            'past'         => $past,
            'toConfirm'    => Appointment::where('status', Appointment::REQUESTED)->where('starts_at', '>=', now())->count(),
        ]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Appointment::statuses()))],
        ]);

        $appointment->update($data);

        return back()->with('status', 'Viewing on '.$appointment->whenLabel().' marked '.strtolower($appointment->statusLabel()).'.');
    }
}
