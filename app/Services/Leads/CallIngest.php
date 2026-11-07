<?php

namespace App\Services\Leads;

use App\Models\Appointment;
use App\Models\Call;
use App\Models\Enquiry;
use App\Models\Property;
use App\Services\Listings\PropertySearch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Takes a finished call from the voice agent and turns it into a qualified
 * lead with a decided next step.
 *
 * Accepts the provider's webhook body exactly as sent ({event, call}) or the
 * bare call object, so the workflow tool can forward it without mapping.
 * Every delivery of a call updates one row (keyed by its call id). The lead
 * is only qualified once the call's analysis has arrived, and only once:
 * a repeat delivery gets the same answer back, marked `already_processed`,
 * so the workflow can skip sending messages twice.
 */
class CallIngest
{
    public function __construct(
        private readonly LeadRecorder $leads,
        private readonly PropertyMatcher $matcher,
        private readonly LeadQualifier $qualifier,
        private readonly NextActions $next,
        private readonly PropertySearch $listings,
    ) {
    }

    /** @return array<string, mixed> the response for the workflow */
    public function ingest(array $payload): array
    {
        $data = is_array($payload['call'] ?? null) ? $payload['call'] : $payload;
        $event = $payload['event'] ?? null;

        $call = $this->store($data);

        if ($call->processed_at) {
            return ['already_processed' => true] + ($call->outcome ?? []);
        }

        // Nothing to qualify until the conversation has been analysed.
        if (! is_array($data['call_analysis'] ?? null)) {
            return [
                'call_id'   => $call->provider_call_id,
                'event'     => $event,
                'processed' => false,
                'reason'    => 'stored; the lead is qualified when the analysed call arrives (call_analyzed)',
            ];
        }

        return DB::transaction(fn () => $this->process($call));
    }

    private function store(array $data): Call
    {
        $call = Call::firstOrNew(['provider_call_id' => (string) $data['call_id']]);
        $analysis = (array) ($data['call_analysis'] ?? []);

        // Later deliveries add to what earlier ones said; they never blank it.
        $fill = array_filter([
            'agent_id'             => $data['agent_id'] ?? null,
            'direction'            => $data['direction'] ?? null,
            'from_number'          => $data['from_number'] ?? null,
            'to_number'            => $data['to_number'] ?? null,
            'status'               => $data['call_status'] ?? null,
            'started_at'           => $this->time($data['start_timestamp'] ?? null),
            'ended_at'             => $this->time($data['end_timestamp'] ?? null),
            'duration_seconds'     => $this->duration($data),
            'disconnection_reason' => $data['disconnection_reason'] ?? null,
            'transcript'           => $data['transcript'] ?? null,
            'summary'              => $analysis['call_summary'] ?? null,
            'sentiment'            => $analysis['user_sentiment'] ?? null,
            'in_voicemail'         => $analysis['in_voicemail'] ?? null,
            'analysis'             => $analysis['custom_analysis_data'] ?? null,
            'recording_url'        => $data['recording_url'] ?? null,
            'cost_cents'           => $this->cost($data['call_cost'] ?? null),
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        $call->fill($fill)->save();

        return $call;
    }

    private function process(Call $call): array
    {
        $requirements = Requirements::fromAnalysis((array) $call->analysis);
        $phone = $call->direction === 'outbound' ? $call->to_number : $call->from_number;

        $lead = $call->enquiry ?? $this->leads->findOrStart($phone, $requirements['name']);
        $property = $this->property($requirements['property_of_interest']);

        $lead->requirements = Requirements::merge($lead->requirements ?? [], $requirements);
        $lead->email ??= $requirements['email'];
        $lead->type = match (true) {
            ($lead->requirements['intent'] ?? null) === 'sell' => 'appraisal',
            $property !== null || $lead->property_id !== null    => 'property',
            default                                              => $lead->type ?? 'contact',
        };
        $lead->property_id ??= $property?->id;
        $lead->message ??= $call->summary ? 'Phone call: '.$call->summary : null;
        if ($lead->type === 'appraisal' && filled($requirements['selling_property'])) {
            $lead->details = array_merge((array) $lead->details, ['address' => $requirements['selling_property']]);
        }
        $lead->save();

        $call->enquiry()->associate($lead)->save();

        $matches = $this->matcher->match($lead->requirements);
        $appointment = Appointment::where('call_id', $call->id)->latest('id')->first();

        $qualification = $this->qualifier->qualify($lead->requirements, $call, count($matches['properties']), $appointment !== null);
        $lead->forceFill(['qualification' => $qualification, 'priority' => $qualification['grade']])->save();

        $decision = $this->next->decide($lead->refresh(), $qualification, $matches['properties'], $appointment, $call);

        $outcome = [
            'call_id'        => $call->provider_call_id,
            'processed'      => true,
            'lead'           => [
                'id'        => $lead->id,
                'name'      => $lead->name,
                'phone'     => $lead->phone,
                'email'     => $lead->email,
                'is_new'    => $lead->wasRecentlyCreated,
                'admin_url' => route('admin.enquiries.show', $lead),
            ],
            'requirements'   => Requirements::stated($lead->requirements),
            'qualification'  => Arr::except($qualification, ['qualified_at']),
            'matches'        => $matches,
            'appointment'    => $appointment ? ['id' => $appointment->id, 'when' => $appointment->whenLabel(), 'status' => $appointment->status] : null,
            'next_actions'   => $decision['actions'],
            'follow_up_at'   => $decision['follow_up_at'],
        ];

        $call->forceFill(['outcome' => $outcome, 'processed_at' => now()])->save();

        return $outcome;
    }

    /** The listing they asked about, by slug or by (part of) its title. */
    private function property(?string $said): ?Property
    {
        if (blank($said)) {
            return null;
        }

        return $this->listings->find(Str::slug($said))
            ?? $this->listings->find($said)
            ?? Property::published()->where('title', 'like', '%'.PropertySearch::stripWildcards($said).'%')->first();
    }

    private function time(mixed $milliseconds): ?CarbonImmutable
    {
        return is_numeric($milliseconds) && $milliseconds > 0
            ? CarbonImmutable::createFromTimestampMs((int) $milliseconds)
            : null;
    }

    private function duration(array $data): ?int
    {
        if (is_numeric($data['duration_ms'] ?? null)) {
            return (int) round($data['duration_ms'] / 1000);
        }

        if (is_numeric($data['start_timestamp'] ?? null) && is_numeric($data['end_timestamp'] ?? null)) {
            return (int) round(($data['end_timestamp'] - $data['start_timestamp']) / 1000);
        }

        return null;
    }

    /** Cents, whether the provider sent a number or {combined_cost: ...}. */
    private function cost(mixed $cost): ?int
    {
        $value = is_array($cost) ? ($cost['combined_cost'] ?? null) : $cost;

        return is_numeric($value) ? (int) round($value) : null;
    }
}
