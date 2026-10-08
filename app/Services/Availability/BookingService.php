<?php

namespace App\Services\Availability;

use App\Models\Reservation;
use App\Models\SlotOccupancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Write-side: creates and releases reservations with CORRECT concurrency.
 *
 * The oversell hazard: two guests pass an advisory availability check for the
 * same tight slot within milliseconds, then both write. We close it by making
 * check-and-write atomic:
 *
 *   1. insertOrIgnore the spanned slot rows (so they exist to be locked)
 *   2. SELECT ... FOR UPDATE those rows (serializes concurrent bookers)
 *   3. re-decide against the freshly locked covers (authoritative)
 *   4. only then increment covers + create the reservation
 *
 * The advisory AvailabilityService::check() is for conversation UX; THIS is the
 * source of truth. Never create a reservation outside book().
 */
class BookingService
{
    public function __construct(private AvailabilityService $availability) {}

    /**
     * Attempt to book. Returns ['result' => AvailabilityResult, 'reservation' => ?Reservation].
     * A reservation is present only when result->confirmable() is true.
     */
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

        // Reject past datetimes up front (advisory check also does this).
        $advisory = $this->check($date, $time, $party);
        if (! $advisory->confirmable()) {
            return ['result' => $advisory, 'reservation' => null];
        }

        $snapped = SlotCalculator::snapToGrid($time, $cfg['slot_minutes']);
        $spanned = SlotCalculator::spannedSlots($snapped, $cfg['turn_minutes'], $cfg['slot_minutes']);

        return DB::transaction(function () use ($contactId, $date, $snapped, $spanned, $party, $name, $notes, $source, $cfg) {
            // 1. Ensure rows exist so we can lock them.
            $now  = now();
            $rows = array_map(fn ($slot) => [
                'reserved_date' => $date,
                'slot_start'    => $slot,
                'covers'        => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ], $spanned);
            DB::table('slot_occupancy')->insertOrIgnore($rows);

            // 2. Lock them.
            $locked = SlotOccupancy::where('reserved_date', $date)
                ->whereIn('slot_start', $spanned)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($r) => substr((string) $r->slot_start, 0, 5));

            $occupancy = $locked->map(fn ($r) => (int) $r->covers)->all();

            // 3. Authoritative re-decision on locked data.
            $result = AvailabilityService::decide($cfg, $occupancy, $party, $snapped, $date);
            if (! $result->confirmable()) {
                return ['result' => $result, 'reservation' => null];
            }

            // 4. Commit covers + reservation.
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

    /**
     * Give a reservation's covers back to its slots. Used by cancellation and
     * as the first half of a modify (T5). Does NOT change reservation status —
     * the caller owns that. Idempotency: pass a reservation that still holds
     * its covers; call once per cancellation.
     */
    public function release(Reservation $reservation): void
    {
        $date = $reservation->reserved_date instanceof Carbon
            ? $reservation->reserved_date->toDateString()
            : (string) $reservation->reserved_date;

        $cfg = $this->availability->configFor($date);
        if (! $cfg) {
            return;
        }

        $snapped = SlotCalculator::snapToGrid(substr((string) $reservation->reserved_time, 0, 5), $cfg['slot_minutes']);
        $spanned = SlotCalculator::spannedSlots($snapped, $cfg['turn_minutes'], $cfg['slot_minutes']);
        $party   = (int) $reservation->party_size;

        DB::transaction(function () use ($date, $spanned, $party) {
            $locked = SlotOccupancy::where('reserved_date', $date)
                ->whereIn('slot_start', $spanned)
                ->lockForUpdate()
                ->get();

            foreach ($locked as $row) {
                $row->covers = max(0, (int) $row->covers - $party);
                $row->save();
            }
        });
    }

    /**
     * Advisory availability with a past-time guard (today's earlier slots are
     * excluded; a wholly past date is invalid).
     */
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

        // On today's date, downgrade a past start to outside_hours and only
        // suggest future slots.
        if ($date === $today && $result->time) {
            $nowHhmm = now()->format('H:i');
            if (SlotCalculator::timeToMinutes($result->time) < SlotCalculator::timeToMinutes($nowHhmm)
                && $result->status === 'available') {
                $alts = AvailabilityService::suggest($cfg, $this->availability->dayOccupancy($date), $party, $result->time, $nowHhmm);
                return new AvailabilityResult('outside_hours', $date, $result->time, $party, $alts, $cfg['auto_confirm_max']);
            }
        }

        return $result;
    }
}
