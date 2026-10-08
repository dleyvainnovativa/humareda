<?php

namespace App\Services\Availability;

use App\Models\Reservation;
use App\Models\SlotOccupancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Write-side: create, release, and MODIFY reservations with correct concurrency.
 * (T5 adds modify() + checkModify() to the T3 version — this file replaces it.)
 *
 * The oversell guard (book): insertOrIgnore spanned slot rows -> SELECT FOR
 * UPDATE -> re-decide on locked covers -> increment + create. Never create a
 * Reservation outside book()/modify().
 */
class BookingService
{
    public function __construct(private AvailabilityService $availability) {}

    // ----------------------------------------------------------------- book

    public function book(
        int $contactId,
        string $date,
        string $time,
        int $party,
        string $name,
        ?string $notes = null,
        string $source = 'bot',
    ): array {
        $cfg = $this->availability->configFor($date);
        if (! $cfg) {
            return ['result' => new AvailabilityResult('closed', $date, null, $party), 'reservation' => null];
        }

        $advisory = $this->check($date, $time, $party);
        if (! $advisory->confirmable()) {
            return ['result' => $advisory, 'reservation' => null];
        }

        $snapped = SlotCalculator::snapToGrid($time, $cfg['slot_minutes']);
        $spanned = SlotCalculator::spannedSlots($snapped, $cfg['turn_minutes'], $cfg['slot_minutes']);

        return DB::transaction(function () use ($contactId, $date, $snapped, $spanned, $party, $name, $notes, $source, $cfg) {
            $this->ensureRows($date, $spanned);

            $locked = $this->lock($date, $spanned);
            $occupancy = $locked->map(fn ($r) => (int) $r->covers)->all();

            $result = AvailabilityService::decide($cfg, $occupancy, $party, $snapped, $date);
            if (! $result->confirmable()) {
                return ['result' => $result, 'reservation' => null];
            }

            foreach ($spanned as $slot) {
                $row = $locked->get($slot);
                $row->covers += $party;
                $row->save();
            }

            $reservation = Reservation::create([
                'contact_id'    => $contactId,
                'reserved_date' => $date,
                'reserved_time' => $snapped,
                'party_size'    => $party,
                'name'          => $name,
                'status'        => Reservation::STATUS_CONFIRMED,
                'source'        => $source,
                'notes'         => $notes,
            ]);

            return ['result' => $result, 'reservation' => $reservation];
        });
    }

    // --------------------------------------------------------------- modify

    /**
     * Atomically move/resize a reservation: release its old covers and take the
     * new ones in ONE locked transaction, re-checking availability against the
     * new slots WITH the reservation's own old covers discounted (so changing
     * only the party size, or shifting within an overlapping window, doesn't
     * see itself as competition). On failure the original is untouched.
     *
     * @return array{result:AvailabilityResult,reservation:?Reservation}
     */
    public function modify(Reservation $r, string $date, string $time, int $party, ?string $name = null): array
    {
        $cfgNew = $this->availability->configFor($date);
        if (! $cfgNew) {
            return ['result' => new AvailabilityResult('closed', $date, null, $party), 'reservation' => null];
        }
        if ($date < now()->toDateString()) {
            return ['result' => new AvailabilityResult('invalid', $date, null, $party, [], $cfgNew['auto_confirm_max']), 'reservation' => null];
        }

        $oldDate  = $this->dateStr($r->reserved_date);
        $cfgOld   = $this->availability->configFor($oldDate) ?? $cfgNew;
        $oldSnap  = SlotCalculator::snapToGrid(substr((string) $r->reserved_time, 0, 5), $cfgOld['slot_minutes']);
        $oldSpan  = SlotCalculator::spannedSlots($oldSnap, $cfgOld['turn_minutes'], $cfgOld['slot_minutes']);
        $oldParty = (int) $r->party_size;

        $newSnap  = SlotCalculator::snapToGrid($time, $cfgNew['slot_minutes']);
        $newSpan  = SlotCalculator::spannedSlots($newSnap, $cfgNew['turn_minutes'], $cfgNew['slot_minutes']);
        $sameDate = $date === $oldDate;

        return DB::transaction(function () use ($r, $date, $oldDate, $newSnap, $newSpan, $oldSpan, $party, $oldParty, $name, $cfgNew, $sameDate) {
            // Lock union of old + new slots in a deterministic (date, slot) order
            // to avoid deadlocks.
            $need = [];
            foreach ($oldSpan as $s) { $need[$oldDate][$s] = true; }
            foreach ($newSpan as $s) { $need[$date][$s]    = true; }
            ksort($need);

            $locked = [];
            foreach ($need as $d => $set) {
                $slots = array_keys($set);
                sort($slots);
                $this->ensureRows($d, $slots);
                foreach ($this->lock($d, $slots) as $slot => $row) {
                    $locked["{$d}|{$slot}"] = $row;
                }
            }

            // Occupancy for the new slots, discounting our own old covers where
            // the old booking overlaps the new (same date + same slot).
            $occNew = [];
            foreach ($newSpan as $s) {
                $cov = (int) ($locked["{$date}|{$s}"]->covers ?? 0);
                if ($sameDate && in_array($s, $oldSpan, true)) {
                    $cov -= $oldParty;
                }
                $occNew[$s] = max(0, $cov);
            }

            $result = AvailabilityService::decide($cfgNew, $occNew, $party, $newSnap, $date);
            if (! $result->confirmable()) {
                return ['result' => $result, 'reservation' => null]; // rollback
            }

            // Release old covers, then take new (overlap nets correctly).
            foreach ($oldSpan as $s) {
                $row = $locked["{$oldDate}|{$s}"];
                $row->covers = max(0, (int) $row->covers - $oldParty);
                $row->save();
            }
            foreach ($newSpan as $s) {
                $row = $locked["{$date}|{$s}"];
                $row->covers += $party;
                $row->save();
            }

            $r->update([
                'reserved_date' => $date,
                'reserved_time' => $newSnap,
                'party_size'    => $party,
                'name'          => $name ?: $r->name,
            ]);

            return ['result' => $result, 'reservation' => $r];
        });
    }

    /**
     * Advisory availability for a modify (no lock/write), with the reservation's
     * own covers discounted. Mirrors modify()'s math for the conversation UX.
     */
    public function checkModify(Reservation $r, string $date, string $time, int $party): AvailabilityResult
    {
        $cfgNew = $this->availability->configFor($date);
        if (! $cfgNew) {
            return new AvailabilityResult('closed', $date, null, $party);
        }
        if ($date < now()->toDateString()) {
            return new AvailabilityResult('invalid', $date, null, $party, [], $cfgNew['auto_confirm_max']);
        }

        $oldDate  = $this->dateStr($r->reserved_date);
        $cfgOld   = $this->availability->configFor($oldDate) ?? $cfgNew;
        $oldSnap  = SlotCalculator::snapToGrid(substr((string) $r->reserved_time, 0, 5), $cfgOld['slot_minutes']);
        $oldSpan  = SlotCalculator::spannedSlots($oldSnap, $cfgOld['turn_minutes'], $cfgOld['slot_minutes']);
        $oldParty = (int) $r->party_size;
        $sameDate = $date === $oldDate;

        $newSnap  = SlotCalculator::snapToGrid($time, $cfgNew['slot_minutes']);
        $newSpan  = SlotCalculator::spannedSlots($newSnap, $cfgNew['turn_minutes'], $cfgNew['slot_minutes']);

        $occ = $this->availability->occupancy($date, $newSpan);
        if ($sameDate) {
            $occ = self::discountOwn($occ, $oldSpan, $oldParty);
        }

        $result = AvailabilityService::decide($cfgNew, $occ, $party, $newSnap, $date);

        if (in_array($result->status, ['full', 'outside_hours'], true)) {
            $day = $this->availability->dayOccupancy($date);
            if ($sameDate) {
                $day = self::discountOwn($day, $oldSpan, $oldParty);
            }
            $alts = AvailabilityService::suggest($cfgNew, $day, $party, $result->time);
            return new AvailabilityResult($result->status, $date, $result->time, $party, $alts, $cfgNew['auto_confirm_max']);
        }

        return $result;
    }

    /**
     * PURE: subtract a reservation's own covers from an occupancy map for the
     * slots it occupies (used by modify math). Unit-tested.
     *
     * @param array<string,int> $occ
     * @param string[] $ownSpan
     * @return array<string,int>
     */
    public static function discountOwn(array $occ, array $ownSpan, int $ownParty): array
    {
        foreach ($ownSpan as $s) {
            if (isset($occ[$s])) {
                $occ[$s] = max(0, $occ[$s] - $ownParty);
            }
        }
        return $occ;
    }

    // --------------------------------------------------------------- release

    public function release(Reservation $reservation): void
    {
        $date = $this->dateStr($reservation->reserved_date);
        $cfg  = $this->availability->configFor($date);
        if (! $cfg) {
            return;
        }

        $snapped = SlotCalculator::snapToGrid(substr((string) $reservation->reserved_time, 0, 5), $cfg['slot_minutes']);
        $spanned = SlotCalculator::spannedSlots($snapped, $cfg['turn_minutes'], $cfg['slot_minutes']);
        $party   = (int) $reservation->party_size;

        DB::transaction(function () use ($date, $spanned, $party) {
            foreach ($this->lock($date, $spanned) as $row) {
                $row->covers = max(0, (int) $row->covers - $party);
                $row->save();
            }
        });
    }

    // ----------------------------------------------------------------- check

    public function check(string $date, string $time, int $party): AvailabilityResult
    {
        $cfg = $this->availability->configFor($date);
        if (! $cfg) {
            return new AvailabilityResult('closed', $date, null, $party);
        }

        $today = now()->toDateString();
        if ($date < $today) {
            return new AvailabilityResult('invalid', $date, null, $party, [], $cfg['auto_confirm_max']);
        }

        $result = $this->availability->check($date, $time, $party);

        if ($date === $today && $result->time && $result->status === 'available') {
            $nowHhmm = now()->format('H:i');
            if (SlotCalculator::timeToMinutes($result->time) < SlotCalculator::timeToMinutes($nowHhmm)) {
                $alts = AvailabilityService::suggest($cfg, $this->availability->dayOccupancy($date), $party, $result->time, $nowHhmm);
                return new AvailabilityResult('outside_hours', $date, $result->time, $party, $alts, $cfg['auto_confirm_max']);
            }
        }

        return $result;
    }

    // --------------------------------------------------------------- helpers

    private function ensureRows(string $date, array $slots): void
    {
        $now  = now();
        $rows = array_map(fn ($slot) => [
            'reserved_date' => $date,
            'slot_start'    => $slot,
            'covers'        => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $slots);
        DB::table('slot_occupancy')->insertOrIgnore($rows);
    }

    /**
     * @return \Illuminate\Support\Collection<string,SlotOccupancy> keyed by 'H:i'
     */
    private function lock(string $date, array $slots)
    {
        return SlotOccupancy::where('reserved_date', $date)
            ->whereIn('slot_start', $slots)
            ->orderBy('slot_start')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn ($r) => substr((string) $r->slot_start, 0, 5));
    }

    private function dateStr($date): string
    {
        return $date instanceof Carbon ? $date->toDateString() : (string) $date;
    }
}
