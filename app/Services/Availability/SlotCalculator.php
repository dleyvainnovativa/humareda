<?php

namespace App\Services\Availability;

/**
 * Pure time/slot arithmetic. No DB, no framework — deliberately, so the
 * off-by-one-prone math can be unit-tested in isolation (see docs/verify_t3.php).
 *
 * A "slot" is a grid bucket of `slotMinutes`. A booking that starts at a
 * grid time occupies ceil(turn / slot) consecutive buckets. Cover accounting
 * is per bucket.
 */
class SlotCalculator
{
    public static function timeToMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));
        return $h * 60 + $m;
    }

    public static function minutesToTime(int $minutes): string
    {
        $minutes = ((int) $minutes) % 1440;
        if ($minutes < 0) {
            $minutes += 1440;
        }
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Round a time to the nearest slot boundary (friendliest for guests who
     * say "8:10" — we seat on the grid).
     */
    public static function snapToGrid(string $hhmm, int $slotMinutes): string
    {
        $min     = self::timeToMinutes($hhmm);
        $snapped = (int) (round($min / $slotMinutes) * $slotMinutes);
        return self::minutesToTime($snapped);
    }

    /**
     * The grid buckets a booking occupies: ceil(turn/slot) buckets starting at
     * the (already grid-aligned) start time.
     *
     * @return string[]  e.g. ['20:00','20:30','21:00','21:30']
     */
    public static function spannedSlots(string $startHhmm, int $turnMinutes, int $slotMinutes): array
    {
        $count = (int) ceil($turnMinutes / $slotMinutes);
        $start = self::timeToMinutes($startHhmm);

        $slots = [];
        for ($i = 0; $i < $count; $i++) {
            $slots[] = self::minutesToTime($start + $i * $slotMinutes);
        }
        return $slots;
    }

    /**
     * All valid start times for a service day: open_time .. last_seating,
     * stepping by slot. Inclusive of last_seating.
     *
     * @return string[]
     */
    public static function daySlots(string $openHhmm, string $lastSeatingHhmm, int $slotMinutes): array
    {
        $open = self::timeToMinutes($openHhmm);
        $last = self::timeToMinutes($lastSeatingHhmm);

        $slots = [];
        for ($t = $open; $t <= $last; $t += $slotMinutes) {
            $slots[] = self::minutesToTime($t);
        }
        return $slots;
    }
}
