<?php

namespace App\Services\Reservations;

use App\Models\Reservation;
use App\Models\ReservationReminder;
use App\Services\Availability\BookingService;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a reservation. (T11) Works for confirmed AND pending (a guest can
 * cancel a request still under review). Frees any held covers (no-op in the
 * advisory model), marks cancelled, and cancels pending reminders.
 */
class CancellationService
{
    public function __construct(private BookingService $booking) {}

    public function cancel(Reservation $reservation): bool
    {
        if (! in_array($reservation->status, [Reservation::STATUS_CONFIRMED, Reservation::STATUS_PENDING], true)) {
            return false;
        }

        // Harmless if no covers were held (advisory model).
        if ($reservation->status === Reservation::STATUS_CONFIRMED) {
            $this->booking->release($reservation);
        }

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
