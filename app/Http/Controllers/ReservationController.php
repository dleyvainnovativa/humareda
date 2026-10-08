<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Reservation;
use App\Services\Reservations\CancellationService;
use App\Services\Reservations\OccupancyReport;
use App\Services\Reservations\ReviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff reservations (T11): a pending queue to authorize, plus day-by-day
 * management. Approve/reject run through ReviewService (messages the guest +
 * sets status). Staff-created bookings are confirmed directly (staff are the
 * authority); covers are not held — load shown is advisory (OccupancyReport).
 */
class ReservationController extends Controller
{
    public function __construct(
        private ReviewService $review,
        private CancellationService $cancellation,
        private OccupancyReport $occupancy,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $date   = $request->query('date', now()->toDateString());
        $pendingCount = Reservation::pending()->upcoming()->count();
        $load = [];

        if ($status === 'pending') {
            $reservations = Reservation::with('contact')->pending()->upcoming()
                ->orderBy('reserved_date')->orderBy('reserved_time')->get();
            foreach ($reservations as $r) {
                $load[$r->id] = $this->occupancy->summary(
                    $r->reserved_date->toDateString(), substr((string) $r->reserved_time, 0, 5)
                );
            }
        } else {
            $q = Reservation::with('contact')->whereDate('reserved_date', $date)
                ->orderBy('reserved_time');
            if ($status !== 'all') {
                $q->where('status', $status);
            }
            $reservations = $q->get();
        }

        $covers = (int) $reservations->where('status', 'confirmed')->sum('party_size');

        return view('reservations.index', compact('reservations', 'status', 'date', 'covers', 'pendingCount', 'load'));
    }

    public function approve(Reservation $reservation): JsonResponse
    {
        $ok = $this->review->approve($reservation, $this->staff());
        return response()->json(['ok' => $ok]);
    }

    public function reject(Reservation $reservation): JsonResponse
    {
        $ok = $this->review->reject($reservation, $this->staff());
        return response()->json(['ok' => $ok]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'             => ['required', 'string', 'max:20'],
            'name'              => ['required', 'string', 'max:120'],
            'reference_contact' => ['nullable', 'string', 'max:160'],
            'date'              => ['required', 'date'],
            'time'              => ['required', 'date_format:H:i'],
            'party_size'        => ['required', 'integer', 'min:1', 'max:500'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ]);

        $contact = Contact::firstOrCreate(
            ['wa_id' => preg_replace('/\D/', '', $data['phone'])],
            ['name' => $data['name']],
        );

        // Staff create = authorized directly (observer schedules the reminder).
        Reservation::create([
            'contact_id'        => $contact->id,
            'reserved_date'     => $data['date'],
            'reserved_time'     => $data['time'],
            'party_size'        => (int) $data['party_size'],
            'name'              => $data['name'],
            'reference_contact' => $data['reference_contact'] ?? null,
            'status'            => Reservation::STATUS_CONFIRMED,
            'source'            => 'staff',
            'notes'             => $data['notes'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'date'       => ['required', 'date'],
            'time'       => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        // Staff edit applies directly (observer reschedules the reminder).
        $reservation->update([
            'reserved_date' => $data['date'],
            'reserved_time' => $data['time'],
            'party_size'    => (int) $data['party_size'],
            'name'          => $data['name'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function cancel(Reservation $reservation): JsonResponse
    {
        $this->cancellation->cancel($reservation);
        return response()->json(['ok' => true]);
    }

    private function staff(): string
    {
        return request()->user()?->email ?? 'staff';
    }
}
