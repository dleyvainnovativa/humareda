<?php

namespace App\Listeners;

use App\Events\ReservationRequested;
use App\Models\Reservation;
use App\Services\Push\PushSender;

/**
 * Pushes staff (Flutter app) when a reservation request needs authorization.
 * Parallel to NotifyStaffOfRequest (email); same event, different channel.
 */
class PushStaffOfRequest
{
    public function __construct(private PushSender $push) {}

    public function handle(ReservationRequested $event): void
    {
        $r    = Reservation::find($event->reservationId);
        $kind = $event->kind === 'modify' ? 'Cambio de reservación' : 'Nueva reservación';

        $detail = $r
            ? "{$r->name} · {$r->party_size} pers · {$r->reserved_date?->toDateString()} " . substr((string) $r->reserved_time, 0, 5)
            : 'Requiere autorización.';

        $this->push->send(
            "{$kind} por autorizar",
            $detail,
            ['type' => 'reservation', 'reservation_id' => $event->reservationId],
        );
    }
}
