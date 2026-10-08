<?php

namespace App\Services\Reservations;

use App\Models\Contact;
use App\Models\Reservation;
use Illuminate\Support\Collection;

/**
 * Finds a contact's reservations for the cancel / modify / status flows.
 * "Upcoming" = confirmed and dated today or later, soonest first.
 */
class ReservationLookup
{
    /** @return Collection<int,Reservation> */
    public function upcoming(Contact $contact): Collection
    {
        return $contact->reservations()
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->whereDate('reserved_date', '>=', now()->toDateString())
            ->orderBy('reserved_date')
            ->orderBy('reserved_time')
            ->get();
    }

    /**
     * Compact arrays for the conversation layer / reply templates.
     * @return array<int,array{id:int,date:string,time:string,party:int,name:string}>
     */
    public function upcomingSummaries(Contact $contact): array
    {
        return $this->upcoming($contact)->map(fn (Reservation $r) => [
            'id'    => $r->id,
            'date'  => $r->reserved_date instanceof \Illuminate\Support\Carbon
                ? $r->reserved_date->toDateString() : (string) $r->reserved_date,
            'time'  => substr((string) $r->reserved_time, 0, 5),
            'party' => (int) $r->party_size,
            'name'  => (string) $r->name,
        ])->all();
    }
}
