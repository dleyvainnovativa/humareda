<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile app — quick reservations view. Read-only: the pending queue (awaiting
 * authorization) plus upcoming confirmed bookings. Authorizing/approving stays
 * in the web panel for v1; this is the at-a-glance view the client asked for.
 */
class AppReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pending = Reservation::pending()->upcoming()
            ->with('contact:id,name,external_id,wa_id')
            ->orderBy('reserved_date')
            ->orderBy('reserved_time')
            ->limit(100)
            ->get()
            ->map(fn (Reservation $r) => $this->serialize($r))
            ->values();

        $upcoming = Reservation::confirmed()->upcoming()
            ->with('contact:id,name,external_id,wa_id')
            ->orderBy('reserved_date')
            ->orderBy('reserved_time')
            ->limit(100)
            ->get()
            ->map(fn (Reservation $r) => $this->serialize($r))
            ->values();

        return response()->json([
            'pending'  => $pending,
            'upcoming' => $upcoming,
        ]);
    }

    private function serialize(Reservation $r): array
    {
        return [
            'id'           => $r->id,
            'name'         => $r->name,
            'party_size'   => $r->party_size,
            'date'         => $r->reserved_date?->toDateString(),
            'time'         => substr((string) $r->reserved_time, 0, 5),
            'status'       => $r->status,
            'source'       => $r->source,
            'notes'        => $r->notes,
            'contact_name' => $r->contact?->name ?: ($r->contact?->external_id ?? $r->contact?->wa_id),
            'created_at'   => $r->created_at?->toIso8601String(),
        ];
    }
}
