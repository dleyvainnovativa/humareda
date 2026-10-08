<?php

namespace App\Services\Reservations;

use App\Models\Reservation;
use App\Models\ReservationReminder;
use App\Services\Availability\BookingService;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a reservation: frees its covers, marks it cancelled, and cancels any
 * pending reminders. Idempotent-ish — cancelling an already-cancelled
 * reservation is a no-op (covers are only released for a confirmed one).
 */
class CancellationService
{
    public function __construct(private BookingService $booking) {}

    public function cancel(Reservation $reservation): bool
    {
        if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
            return false;
        }

        // Give the covers back (own transaction + row lock inside).
        $this->booking->release($reservation);

        DB::transaction(function () use ($reservation) {
            $reservation->update([
                'status'       => Reservation::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            ReservationReminder::where('reservation_id', $reservation->id)
                ->where('status', ReservationReminder::STATUS_PENDING)
                ->update(['status' => ReservationReminder::STATUS_CANCELLED]);
        });

        return true;
    }
}
