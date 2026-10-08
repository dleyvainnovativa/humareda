<?php

namespace App\Services\Reservations;

use App\Models\Contact;
use App\Models\Reservation;
use Illuminate\Support\Collection;

/**
 * Finds a contact's active reservations for cancel / modify / status.
 * (T11) "Active" = confirmed OR pending (under review), dated today or later.
 */
class ReservationLookup
{
    /** @return Collection<int,Reservation> */
    public function upcoming(Contact $contact): Collection
    {
        return $contact->reservations()
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_PENDING])
            ->whereDate('reserved_date', '>=', now()->toDateString())
            ->orderBy('reserved_date')
            ->orderBy('reserved_time')
            ->get();
    }

    /** @return array<int,array{id:int,date:string,time:string,party:int,name:string,status:string}> */
    public function upcomingSummaries(Contact $contact): array
    {
        return $this->upcoming($contact)->map(fn (Reservation $r) => [
            'id'     => $r->id,
            'date'   => $r->reserved_date instanceof \Illuminate\Support\Carbon ? $r->reserved_date->toDateString() : (string) $r->reserved_date,
            'time'   => substr((string) $r->reserved_time, 0, 5),
            'party'  => (int) $r->party_size,
            'name'   => (string) $r->name,
            'status' => $r->status,
        ])->all();
    }
}
