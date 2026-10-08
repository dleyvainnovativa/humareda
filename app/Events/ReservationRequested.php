<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a guest submits a reservation request (new or a modification) that
 * needs human authorization. Listeners notify staff; the panel shows it under
 * "Pendientes".
 */
class ReservationRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $reservationId,
        public int $contactId,
        public string $kind,          // 'new' | 'modify'
        public ?string $waId = null,
        public ?string $contactName = null,
    ) {}
}
