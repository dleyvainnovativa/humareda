<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Services\Reminders\ReminderScheduler;

/**
 * Keeps reminders in sync with reservations without touching the booking flow:
 *   - confirmed booking created        -> schedule
 *   - date/time changed (modify)       -> reschedule
 *   - status changed to cancelled      -> cancel pending reminders
 *
 * Runs inside the booking/modify transactions (Reservation::create/update), so
 * the reminder row commits atomically with the reservation.
 *
 * Register in AppServiceProvider::boot():
 *   Reservation::observe(ReservationObserver::class);
 */
class ReservationObserver
{
    public function __construct(private ReminderScheduler $scheduler) {}

    public function created(Reservation $reservation): void
    {
        if ($reservation->status === Reservation::STATUS_CONFIRMED) {
            $this->scheduler->schedule($reservation);
        }
    }

    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged('status') && $reservation->status === Reservation::STATUS_CANCELLED) {
            $this->scheduler->cancelFor($reservation);
            return;
        }

        if ($reservation->status === Reservation::STATUS_CONFIRMED
            && ($reservation->wasChanged('reserved_date') || $reservation->wasChanged('reserved_time'))) {
            $this->scheduler->schedule($reservation); // reschedule
        }
    }
}
