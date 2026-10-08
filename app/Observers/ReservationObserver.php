<?php

namespace App\Observers;

use App\Models\Reservation;
use App\Services\Reminders\ReminderScheduler;

/**
 * Keeps reminders in sync with reservation lifecycle (T11 model):
 *   - status -> confirmed (staff approval)         => schedule reminder
 *   - confirmed + date/time changed                => reschedule
 *   - status -> cancelled | rejected               => cancel pending reminders
 *
 * Pending requests get NO reminder (they aren't confirmed yet).
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
            $this->scheduler->schedule($reservation); // e.g. staff direct-create
        }
    }

    public function updated(Reservation $reservation): void
    {
        if ($reservation->wasChanged('status')) {
            if ($reservation->status === Reservation::STATUS_CONFIRMED) {
                $this->scheduler->schedule($reservation);   // approval
                return;
            }
            if (in_array($reservation->status, [Reservation::STATUS_CANCELLED, Reservation::STATUS_REJECTED], true)) {
                $this->scheduler->cancelFor($reservation);
                return;
            }
        }

        if ($reservation->status === Reservation::STATUS_CONFIRMED
            && ($reservation->wasChanged('reserved_date') || $reservation->wasChanged('reserved_time'))) {
            $this->scheduler->schedule($reservation); // reschedule
        }
    }
}
