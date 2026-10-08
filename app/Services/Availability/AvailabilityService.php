<?php

namespace App\Services\Availability;

use App\Models\ServiceHour;
use App\Models\SlotOccupancy;
use Illuminate\Support\Carbon;

/**
 * Read-side availability. Loads the weekday config + current cover occupancy
 * and evaluates whether a request is confirmable.
 *
 * The decision math lives in the pure static decide()/suggest() so it can be
 * unit-tested without a database. The instance methods just fetch data and
 * delegate. BookingService re-runs decide() INSIDE a row lock before writing —
 * this class is advisory; the lock is authoritative.
 */
class AvailabilityService
{
    /**
     * Config array for the weekday of $date, or null if no config row exists.
     *
     * @return array{is_open:bool,open_time:string,last_seating:string,slot_minutes:int,turn_minutes:int,max_covers_per_slot:int,auto_confirm_max:int}|null
     */
    public function configFor(string $date): ?array
    {
        $dow = Carbon::parse($date)->dayOfWeek; // 0=Sun..6=Sat
        $row = ServiceHour::where('day_of_week', $dow)->first();
        if (! $row) {
            return null;
        }

        return [
            'is_open'             => (bool) $row->is_open,
            'open_time'           => substr((string) $row->open_time, 0, 5),
            'last_seating'        => substr((string) $row->last_seating, 0, 5),
            'slot_minutes'        => (int) $row->slot_minutes,
            'turn_minutes'        => (int) $row->turn_minutes,
            'max_covers_per_slot' => (int) $row->max_covers_per_slot,
            'auto_confirm_max'    => (int) $row->auto_confirm_max,
        ];
    }

    /**
     * Covers currently booked for the given date + slot times.
     *
     * @param string[] $slotTimes
     * @return array<string,int>  slot 'H:i' => covers
     */
    public function occupancy(string $date, array $slotTimes): array
    {
        if (! $slotTimes) {
            return [];
        }

        return SlotOccupancy::where('reserved_date', $date)
            ->whereIn('slot_start', $slotTimes)
            ->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->slot_start, 0, 5) => (int) $r->covers])
            ->all();
    }

    /**
     * Full-day occupancy map (used to compute alternatives).
     *
     * @return array<string,int>
     */
    public function dayOccupancy(string $date): array
    {
        return SlotOccupancy::where('reserved_date', $date)
            ->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->slot_start, 0, 5) => (int) $r->covers])
            ->all();
    }

    /**
     * Public entry point: is (date, time, party) confirmable?
     */
    public function check(string $date, string $time, int $party): AvailabilityResult
    {
        $cfg = $this->configFor($date);
        if (! $cfg) {
            return new AvailabilityResult('closed', $date, null, $party);
        }

        $snapped = $cfg['is_open']
            ? SlotCalculator::snapToGrid($time, $cfg['slot_minutes'])
            : $time;

        $spanned   = SlotCalculator::spannedSlots($snapped, $cfg['turn_minutes'], $cfg['slot_minutes']);
        $occupancy = $this->occupancy($date, $spanned);

        $result = self::decide($cfg, $occupancy, $party, $time, $date);

        // Attach same-day alternatives when the ask can't be met.
        if (in_array($result->status, ['full', 'outside_hours'], true)) {
            $alts = self::suggest($cfg, $this->dayOccupancy($date), $party, $result->time);
            return new AvailabilityResult(
                $result->status, $date, $result->time, $party, $alts, $cfg['auto_confirm_max'],
            );
        }

        return $result;
    }

    // ----------------------------------------------------------------------
    // Pure decision logic (no DB) — unit-tested in docs/verify_t3.php
    // ----------------------------------------------------------------------

    /**
     * Evaluate a single request against config + the occupancy of its spanned
     * slots. `$occupancy` must cover exactly the spanned slots (missing = 0).
     */
    public static function decide(array $cfg, array $occupancy, int $party, string $requestedTime, string $date): AvailabilityResult
    {
        if (! $cfg['is_open']) {
            return new AvailabilityResult('closed', $date, null, $party, [], $cfg['auto_confirm_max']);
        }

        if ($party < 1) {
            return new AvailabilityResult('invalid', $date, null, $party, [], $cfg['auto_confirm_max']);
        }

        $snapped   = SlotCalculator::snapToGrid($requestedTime, $cfg['slot_minutes']);
        $startMin  = SlotCalculator::timeToMinutes($snapped);
        $openMin   = SlotCalculator::timeToMinutes($cfg['open_time']);
        $lastMin   = SlotCalculator::timeToMinutes($cfg['last_seating']);

        if ($startMin < $openMin || $startMin > $lastMin) {
            return new AvailabilityResult('outside_hours', $date, $snapped, $party, [], $cfg['auto_confirm_max']);
        }

        // Big parties always go to a human, even if the room could fit them.
        if ($party > $cfg['auto_confirm_max']) {
            return new AvailabilityResult('needs_human', $date, $snapped, $party, [], $cfg['auto_confirm_max']);
        }

        // A party larger than a whole slot's capacity can never fit.
        if ($party > $cfg['max_covers_per_slot']) {
            return new AvailabilityResult('invalid', $date, $snapped, $party, [], $cfg['auto_confirm_max']);
        }

        $spanned = SlotCalculator::spannedSlots($snapped, $cfg['turn_minutes'], $cfg['slot_minutes']);
        if (self::fits($occupancy, $spanned, $party, $cfg['max_covers_per_slot'])) {
            return new AvailabilityResult('available', $date, $snapped, $party, [], $cfg['auto_confirm_max']);
        }

        return new AvailabilityResult('full', $date, $snapped, $party, [], $cfg['auto_confirm_max']);
    }

    /**
     * Every spanned slot must have room for the whole party.
     *
     * @param array<string,int> $occupancy
     * @param string[] $spanned
     */
    public static function fits(array $occupancy, array $spanned, int $party, int $max): bool
    {
        foreach ($spanned as $slot) {
            if (($occupancy[$slot] ?? 0) + $party > $max) {
                return false;
            }
        }
        return true;
    }

    /**
     * Up to 3 same-day start times (nearest to the requested time) where the
     * party fits and doesn't exceed auto_confirm_max.
     *
     * @param array<string,int> $dayOccupancy
     * @return string[]
     */
    public static function suggest(array $cfg, array $dayOccupancy, int $party, ?string $around, ?string $minStart = null): array
    {
        if (! $cfg['is_open'] || $party < 1 || $party > $cfg['auto_confirm_max'] || $party > $cfg['max_covers_per_slot']) {
            return [];
        }

        $candidates = SlotCalculator::daySlots($cfg['open_time'], $cfg['last_seating'], $cfg['slot_minutes']);
        $aroundMin  = $around ? SlotCalculator::timeToMinutes($around) : SlotCalculator::timeToMinutes($cfg['open_time']);
        $minMin     = $minStart !== null ? SlotCalculator::timeToMinutes($minStart) : null;

        $open = [];
        foreach ($candidates as $slot) {
            if ($minMin !== null && SlotCalculator::timeToMinutes($slot) < $minMin) {
                continue; // skip slots already in the past (today)
            }
            $spanned = SlotCalculator::spannedSlots($slot, $cfg['turn_minutes'], $cfg['slot_minutes']);
            if (self::fits($dayOccupancy, $spanned, $party, $cfg['max_covers_per_slot'])) {
                $open[] = $slot;
            }
        }

        // Sort by proximity to the requested time, then take 3, then chronological.
        usort($open, function ($a, $b) use ($aroundMin) {
            return abs(SlotCalculator::timeToMinutes($a) - $aroundMin)
                <=> abs(SlotCalculator::timeToMinutes($b) - $aroundMin);
        });

        $nearest = array_slice($open, 0, 3);
        usort($nearest, fn ($a, $b) => SlotCalculator::timeToMinutes($a) <=> SlotCalculator::timeToMinutes($b));

        return $nearest;
    }
}
