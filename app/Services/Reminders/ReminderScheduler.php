<?php

namespace App\Services\Reminders;

use App\Models\Reservation;
use App\Models\ReservationReminder;
use App\Models\Setting;

/**
 * Schedules the same-day reminder for a reservation. Driven by settings:
 *   reminder_mode = fixed_time  -> reminder on the reservation date at reminder_fixed_time (e.g. 11:00)
 *   reminder_mode = hours_before -> reminder N hours before the seating time
 *
 * Scheduling is idempotent per reservation: it cancels any pending reminder and
 * creates a fresh one. If the computed time is already in the past (e.g. a
 * same-day booking made after the fixed time), no reminder is created.
 *
 * The time math is a pure static (computeAt) using PHP's native DateTime so it
 * is unit-testable without the framework.
 */
class ReminderScheduler
{
    public const TZ = 'America/Mexico_City';

    public function schedule(Reservation $reservation): void
    {
        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            return;
        }

        // Clear any existing pending reminder first (reschedule semantics).
        ReservationReminder::where('reservation_id', $reservation->id)
            ->where('status', ReservationReminder::STATUS_PENDING)
            ->update(['status' => ReservationReminder::STATUS_CANCELLED]);

        $at = self::computeAt(
            (string) Setting::get('reminder_mode', 'fixed_time'),
            (string) Setting::get('reminder_fixed_time', '11:00'),
            (int) Setting::get('reminder_hours_before', 3),
            $this->dateStr($reservation->reserved_date),
            substr((string) $reservation->reserved_time, 0, 5),
            now(self::TZ)->format('Y-m-d H:i:s'),
            self::TZ,
        );

        if ($at === null) {
            return; // computed time already passed — no same-day reminder
        }

        ReservationReminder::create([
            'reservation_id' => $reservation->id,
            'type'           => 'same_day',
            'scheduled_at'   => $at,
            'status'         => ReservationReminder::STATUS_PENDING,
        ]);
    }

    public function cancelFor(Reservation $reservation): void
    {
        ReservationReminder::where('reservation_id', $reservation->id)
            ->where('status', ReservationReminder::STATUS_PENDING)
            ->update(['status' => ReservationReminder::STATUS_CANCELLED]);
    }

    /**
     * PURE: compute the reminder time, or null if it's not in the future.
     * Returns a 'Y-m-d H:i:s' string in the given timezone.
     */
    public static function computeAt(
        string $mode,
        string $fixedTime,
        int $hoursBefore,
        string $date,
        string $time,
        string $nowStr,
        string $tz = self::TZ,
    ): ?string {
        $zone = new \DateTimeZone($tz);
        $now  = new \DateTimeImmutable($nowStr, $zone);

        if ($mode === 'hours_before') {
            $seating = new \DateTimeImmutable("{$date} {$time}", $zone);
            $at = $seating->modify("-{$hoursBefore} hours");
        } else { // fixed_time
            $at = new \DateTimeImmutable("{$date} {$fixedTime}", $zone);
        }

        if ($at <= $now) {
            return null;
        }
        return $at->format('Y-m-d H:i:s');
    }

    private function dateStr($date): string
    {
        return $date instanceof \Illuminate\Support\Carbon ? $date->toDateString() : (string) $date;
    }
}
