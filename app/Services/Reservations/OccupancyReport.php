<?php

namespace App\Services\Reservations;

use App\Models\Reservation;
use App\Models\ServiceHour;
use Illuminate\Support\Carbon;

/**
 * Advisory load, computed live from CONFIRMED reservations (T11 — covers are no
 * longer held; this is a decision aid for staff authorizing requests). No
 * locking, no slot_occupancy dependency.
 */
class OccupancyReport
{
    /**
     * @return array{day:int, around:int, turn:int}
     *   day    = confirmed covers that date
     *   around = confirmed covers whose seating is within one turn of $time
     */
    public function summary(string $date, string $time): array
    {
        $turn = $this->turnMinutes($date);

        $confirmed = Reservation::confirmed()
            ->whereDate('reserved_date', $date)
            ->get(['reserved_time', 'party_size']);

        $req = $this->toMin($time);
        $day = 0; $around = 0;
        foreach ($confirmed as $r) {
            $day += (int) $r->party_size;
            if (abs($this->toMin(substr((string) $r->reserved_time, 0, 5)) - $req) < $turn) {
                $around += (int) $r->party_size;
            }
        }

        return ['day' => $day, 'around' => $around, 'turn' => $turn];
    }

    private function turnMinutes(string $date): int
    {
        $dow = Carbon::parse($date)->dayOfWeek;
        return (int) (ServiceHour::where('day_of_week', $dow)->value('turn_minutes') ?? 120);
    }

    private function toMin(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));
        return $h * 60 + $m;
    }
}
