<?php

namespace App\Services\Reservations;

use App\Events\ReservationRequested;
use App\Models\Contact;
use App\Models\Reservation;

/**
 * Captures reservation requests as PENDING (T11). No covers are held and no
 * availability is enforced — a human authorizes from the panel to avoid
 * clashes. Fires ReservationRequested so staff are notified.
 */
class RequestService
{
    /**
     * @param array{name:string,party_size:int,time:string,date:string,reference_contact:?string} $slots
     */
    public function submitNew(Contact $contact, array $slots): Reservation
    {
        $reservation = Reservation::create([
            'contact_id'        => $contact->id,
            'reserved_date'     => $slots['date'],
            'reserved_time'     => $slots['time'],
            'party_size'        => (int) $slots['party_size'],
            'name'              => $slots['name'],
            'reference_contact' => $slots['reference_contact'] ?? null,
            'status'            => Reservation::STATUS_PENDING,
            'source'            => 'bot',
        ]);

        event(new ReservationRequested(
            $reservation->id, $contact->id, 'new', $contact->wa_id, $contact->name,
        ));

        return $reservation;
    }

    /**
     * A change to an existing reservation re-enters review: we update the
     * requested values and flip it back to pending for re-authorization.
     */
    public function submitModification(Reservation $reservation, array $slots): Reservation
    {
        $reservation->update([
            'reserved_date'     => $slots['date'],
            'reserved_time'     => $slots['time'],
            'party_size'        => (int) $slots['party_size'],
            'name'              => $slots['name'] ?: $reservation->name,
            'reference_contact' => $slots['reference_contact'] ?? $reservation->reference_contact,
            'status'            => Reservation::STATUS_PENDING,
            'reviewed_at'       => null,
            'reviewed_by'       => null,
        ]);

        event(new ReservationRequested(
            $reservation->id, $reservation->contact_id, 'modify',
            $reservation->contact?->wa_id, $reservation->contact?->name,
        ));

        return $reservation;
    }
}
