<?php

namespace App\Services\Leads;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Free viewing slots, from the agent's weekly hours minus what is booked.
 *
 * Hours live in config/agent.php in the agent's own timezone; slots are
 * returned with that offset, plus a spoken-style label the voice agent can
 * read out ("Saturday 11 October, 3:00pm").
 */
class Availability
{
    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** @return list<array{starts_at: string, ends_at: string, label: string}> */
    public function slots(?CarbonImmutable $from = null, ?int $days = null): array
    {
        $config = config('agent.appointments');
        $tz = $config['timezone'];
        $length = (int) $config['slot_minutes'];
        $earliest = CarbonImmutable::now($tz)->addHours((int) $config['min_notice_hours']);
        $from = ($from ?? $earliest)->setTimezone($tz)->startOfDay();
        $days = min($days ?? (int) $config['days_ahead'], (int) $config['days_ahead']);
        $until = CarbonImmutable::now($tz)->addDays((int) $config['days_ahead'])->endOfDay();

        // Booked rows are stored as wall-clock in the agent's timezone, the
        // same clock these slots are built from.
        $booked = Appointment::active()
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at']);

        $slots = [];
        for ($d = 0; $d < $days; $d++) {
            $day = $from->addDays($d);
            if ($day->greaterThan($until)) {
                break;
            }

            foreach ($config['hours'][self::DAYS[$day->dayOfWeekIso - 1]] ?? [] as [$open, $close]) {
                $start = $day->setTimeFromTimeString($open);
                $end = $day->setTimeFromTimeString($close);

                for ($slot = $start; $slot->addMinutes($length)->lessThanOrEqualTo($end); $slot = $slot->addMinutes($length)) {
                    $slotEnd = $slot->addMinutes($length);
                    if ($slot->lessThan($earliest) || $this->clashes($booked, $slot, $slotEnd)) {
                        continue;
                    }

                    $slots[] = [
                        'starts_at' => $slot->toIso8601String(),
                        'ends_at'   => $slotEnd->toIso8601String(),
                        'label'     => $slot->format('l j F, g:ia'),
                    ];
                }
            }
        }

        return $slots;
    }

    /** Whether a requested start is a real, free slot. */
    public function isFree(CarbonImmutable $start): bool
    {
        $local = $start->setTimezone(config('agent.appointments.timezone'));

        return collect($this->slots($local->startOfDay(), 1))->contains('starts_at', $local->toIso8601String());
    }

    private function clashes(Collection $booked, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $booked->contains(fn(Appointment $a) => $a->starts_at->lt($end) && $a->ends_at->gt($start));
    }
}
