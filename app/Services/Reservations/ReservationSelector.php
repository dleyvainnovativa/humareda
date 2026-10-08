<?php

namespace App\Services\Reservations;

/**
 * Pure disambiguation: given several candidate reservations and the guest's
 * reply, pick which one they mean. Order of preference:
 *   1. an explicit list number ("2")
 *   2. a date match (from the LLM-extracted date slot)
 *   3. a time match (extracted time)
 * Returns the reservation id, or null if still ambiguous.
 *
 * @phpstan-type Candidate array{id:int,date:string,time:string,party:int,name:string}
 */
class ReservationSelector
{
    /**
     * @param array<int,array{id:int,date:string,time:string,party:int,name:string}> $candidates
     * @param array{date?:?string,time?:?string} $interpSlots
     */
    public static function pick(array $candidates, string $text, array $interpSlots = []): ?int
    {
        if (! $candidates) {
            return null;
        }

        // 1. List number (1-based), only if within range.
        if (preg_match('/\b([1-9])\b/', $text, $m)) {
            $idx = (int) $m[1] - 1;
            if ($idx >= 0 && $idx < count($candidates)) {
                return $candidates[$idx]['id'];
            }
        }

        // 2. Date match.
        $date = $interpSlots['date'] ?? null;
        if ($date) {
            $byDate = array_values(array_filter($candidates, fn ($c) => $c['date'] === $date));
            if (count($byDate) === 1) {
                return $byDate[0]['id'];
            }
            // If date narrows to several, try to further split by time.
            $time = $interpSlots['time'] ?? null;
            if ($time && $byDate) {
                $byTime = array_values(array_filter($byDate, fn ($c) => $c['time'] === $time));
                if (count($byTime) === 1) {
                    return $byTime[0]['id'];
                }
            }
        }

        // 3. Time match alone.
        $time = $interpSlots['time'] ?? null;
        if ($time) {
            $byTime = array_values(array_filter($candidates, fn ($c) => $c['time'] === $time));
            if (count($byTime) === 1) {
                return $byTime[0]['id'];
            }
        }

        return null;
    }
}
