<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Call;
use App\Services\Leads\Availability;
use App\Services\Leads\LeadRecorder;
use App\Services\Listings\PropertySearch;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/** Viewing slots and bookings, for the phone agent during the call. */
class AppointmentController extends Controller
{
    use ReadsToolInput;

    public function availability(Request $request, Availability $availability): JsonResponse
    {
        $input = $this->toolInput($request);
        $from = filled($input['date'] ?? null)
            ? $this->parse($input['date'], startOfDay: true)
            : null;

        $slots = $availability->slots($from, (int) ($input['days'] ?? 3));

        return response()->json([
            'timezone' => config('agent.appointments.timezone'),
            'slots'    => array_slice($slots, 0, (int) ($input['limit'] ?? 12)),
            'note'     => $slots === [] ? 'No free viewing times in that period.' : null,
        ]);
    }

    /**
     * Book a viewing. Always 200 so a voice agent can read the answer: when
     * the time is taken or outside hours, `booked` is false and the nearest
     * free times come back to offer instead.
     */
    public function store(Request $request, Availability $availability, LeadRecorder $leads, PropertySearch $listings): JsonResponse
    {
        $input = $this->toolInput($request);
        $input['phone'] ??= $this->callerPhone($request);
        $input['call_id'] ??= $request->input('call.call_id');

        $data = Validator::make($input, [
            'starts_at'     => ['required', 'string', 'max:40'],
            'phone'         => ['required', 'string', 'max:40'],
            'name'          => ['nullable', 'string', 'max:120'],
            'property_slug' => ['nullable', 'string', 'max:255'],
            'notes'         => ['nullable', 'string', 'max:1000'],
            'call_id'       => ['nullable', 'string', 'max:255'],
        ])->validate();

        $start = $this->parse($data['starts_at']);

        if (! $start || ! $availability->isFree($start)) {
            return response()->json([
                'booked'       => false,
                'reason'       => $start ? 'That time is not available.' : 'Could not understand that time.',
                'alternatives' => array_slice($availability->slots($start, 3), 0, 3),
            ]);
        }

        $appointment = DB::transaction(function () use ($data, $start, $leads, $listings) {
            $lead = $leads->findOrStart($data['phone'], $data['name'] ?? null);
            $lead->save();

            $property = $listings->find($data['property_slug'] ?? null);
            $call = filled($data['call_id'] ?? null)
                ? Call::firstOrCreate(['provider_call_id' => $data['call_id']])
                : null;

            return Appointment::create([
                'enquiry_id'  => $lead->id,
                'property_id' => $property?->id,
                'call_id'     => $call?->id,
                // Stored as wall-clock in the agent's timezone (config timezone
                // and appointment timezone are the same), which is how the
                // availability slots are generated and compared.
                'starts_at'   => $start,
                'ends_at'     => $start->addMinutes((int) config('agent.appointments.slot_minutes')),
                'status'      => config('agent.appointments.auto_confirm') ? Appointment::CONFIRMED : Appointment::REQUESTED,
                'source'      => 'voice',
                'notes'       => $data['notes'] ?? null,
            ]);
        });

        return response()->json([
            'booked'      => true,
            'appointment' => [
                'id'     => $appointment->id,
                'when'   => $appointment->whenLabel(),
                'status' => $appointment->status,
            ],
            'say' => $appointment->status === Appointment::CONFIRMED
                ? "You're booked for {$appointment->whenLabel()}."
                : "I've requested {$appointment->whenLabel()} - " . config('agent.name') . ' will confirm it with you shortly.',
        ]);
    }

    /** An ISO time, or a date; read in the agent's timezone when no offset is given. */
    private function parse(string $value, bool $startOfDay = false): ?CarbonImmutable
    {
        try {
            $time = CarbonImmutable::parse($value, config('agent.appointments.timezone'));
        } catch (Throwable) {
            return null;
        }

        return $startOfDay ? $time->setTimezone(config('agent.appointments.timezone'))->startOfDay() : $time;
    }
}
