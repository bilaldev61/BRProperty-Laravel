<?php

namespace App\Services\Leads;

use App\Models\Appointment;
use App\Models\Call;
use App\Models\Enquiry;
use App\Support\EnquiryPriority;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * What should happen after a call, decided here so every workflow tool gets
 * the same answer.
 *
 * n8n does the sending; this decides what to send and to whom. Each action
 * names itself, says who it is for and why, and carries what is needed to do
 * it - so the workflow branches on `action` and never re-implements a rule.
 * It also sets the lead's chase date, so the inbox's "overdue" view works for
 * phone leads too.
 */
class NextActions
{
    /**
     * @param  list<array<string, mixed>>  $matches
     * @return array{actions: list<array<string, mixed>>, follow_up_at: string|null}
     */
    public function decide(Enquiry $lead, array $qualification, array $matches, ?Appointment $appointment, ?Call $call): array
    {
        $r = $lead->requirements ?? [];
        $grade = $qualification['grade'] ?? EnquiryPriority::COLD;
        $actions = [];
        $followUp = null;

        $add = function (string $action, string $for, string $why, array $data = []) use (&$actions) {
            $actions[] = ['action' => $action, 'for' => $for, 'reason' => $why] + $data;
        };

        // No conversation happened: the only useful thing is to try again.
        if ($call && ($call->in_voicemail || ($qualification['score'] ?? 0) === 0)) {
            $add('call_back', 'agent', $qualification['reasons'][0][0] ?? 'no conversation took place', ['phone' => $lead->phone]);

            return $this->result($actions, $lead, $this->nextBusinessMorning());
        }

        if ($appointment) {
            $add('confirm_appointment', 'lead', 'a viewing was booked on the call', [
                'appointment_id' => $appointment->id,
                'when'           => $appointment->whenLabel(),
                'status'         => $appointment->status,
                'message'        => $this->appointmentMessage($lead, $appointment),
            ]);
            if ($appointment->status === Appointment::REQUESTED) {
                $add('approve_appointment', 'agent', 'a requested viewing needs your confirmation', ['appointment_id' => $appointment->id, 'when' => $appointment->whenLabel()]);
            }
        } elseif ($r['appointment_requested'] ?? false) {
            $add('schedule_appointment', 'agent', 'they asked for a viewing but none was booked on the call', [
                'preferred_time' => $r['preferred_time'] ?? null,
                'phone'          => $lead->phone,
            ]);
            $followUp = now()->addHours(4);
        }

        if (($r['intent'] ?? null) === 'sell') {
            $add('book_valuation', 'agent', 'seller lead - arrange a valuation visit', ['selling_property' => $r['selling_property'] ?? null, 'phone' => $lead->phone]);
            $followUp ??= now()->addHours(4);
        }

        if ($grade === EnquiryPriority::HOT) {
            $add('notify_agent', 'agent', 'hot lead - call back today', ['urgency' => 'high', 'summary' => $qualification['summary'] ?? null]);
            $followUp ??= now()->addHours(2);
        }

        if ($matches !== [] && ($r['intent'] ?? null) !== 'sell') {
            $add('send_matches', 'lead', count($matches) . ' ' . Str::plural('listing', count($matches)) . ' fit what they asked for', [
                'properties' => $matches,
                'message'    => $this->matchesMessage($lead, $matches),
            ]);
        }

        if ($matches === [] && ($r['intent'] ?? null) === 'buy') {
            $add('agent_follow_up', 'agent', 'nothing listed fits - follow up with off-market or new options', ['requirements' => Requirements::stated($r)]);
        }

        $followUp ??= match ($grade) {
            EnquiryPriority::HOT  => now()->addHours(2),
            EnquiryPriority::WARM => now()->addDays(2),
            default               => now()->addDays(7),
        };

        if ($grade === EnquiryPriority::COLD && ! $actions) {
            $add('nurture', 'agent', 'early-stage lead - check back in a week');
        }

        return $this->result($actions, $lead, $followUp);
    }

    private function result(array $actions, Enquiry $lead, CarbonInterface $followUp): array
    {
        // Never push an existing earlier chase date later.
        if (! $lead->follow_up_at || $lead->follow_up_at->greaterThan($followUp)) {
            $lead->forceFill(['follow_up_at' => $followUp])->save();
        }

        return ['actions' => $actions, 'follow_up_at' => $lead->follow_up_at?->toIso8601String()];
    }

    /** 10am tomorrow, agent time - a sensible time for a call back. */
    private function nextBusinessMorning(): CarbonImmutable
    {
        return CarbonImmutable::now(config('agent.appointments.timezone'))->addDay()->setTime(10, 0);
    }

    /** Ready to send as a WhatsApp or SMS - built from the records, never by a model. */
    private function matchesMessage(Enquiry $lead, array $matches): string
    {
        $lines = collect($matches)->take(3)->map(fn($m) => "- {$m['title']}, {$m['price']}\n  {$m['url']}")->implode("\n");
        $close = collect($matches)->every(fn($m) => $m['close'] ?? false);

        return sprintf(
            "Hi %s, thanks for calling %s. %s\n%s\nReply here or call %s to arrange a viewing.",
            $lead->name,
            config('agent.agency'),
            $close ? 'Nothing matches exactly right now, but these are close:' : 'Here are listings that match what you are looking for:',
            $lines,
            config('agent.phone'),
        );
    }

    private function appointmentMessage(Enquiry $lead, Appointment $appointment): string
    {
        $where = $appointment->property ? ' to view ' . $appointment->property->title : '';
        $state = $appointment->status === Appointment::CONFIRMED ? 'is confirmed' : 'is requested - ' . config('agent.name') . ' will confirm shortly';

        return "Hi {$lead->name}, your viewing{$where} on {$appointment->whenLabel()} {$state}. Call " . config('agent.phone') . ' if you need to change it.';
    }
}
