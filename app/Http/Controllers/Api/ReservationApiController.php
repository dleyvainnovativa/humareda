<?php

namespace App\Http\Controllers\Api;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Reservation;
use App\Services\Reservations\CancellationService;
use App\Services\Reservations\OccupancyReport;
use App\Services\Reservations\ReviewService;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON API for the Flutter staff app (T14 Phase 4) — reservations module.
 *
 * Mirrors the Blade ReservationController: pending authorization queue +
 * day-by-day management, approve/reject through ReviewService (which messages
 * the guest), staff-created bookings confirmed directly. JSON for the app.
 */
class ReservationApiController extends Controller
{
    public function __construct(
        private ReviewService $review,
        private CancellationService $cancellation,
        private OccupancyReport $occupancy,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');
        $date   = $request->query('date', now()->toDateString());
        $load   = [];

        if ($status === 'pending') {
            $reservations = Reservation::with('contact')->pending()->upcoming()
                ->orderBy('reserved_date')->orderBy('reserved_time')->get();
            foreach ($reservations as $r) {
                $load[$r->id] = $this->occupancy->summary(
                    $r->reserved_date->toDateString(),
                    substr((string) $r->reserved_time, 0, 5),
                );
            }
        } else {
            $q = Reservation::with('contact')->whereDate('reserved_date', $date)->orderBy('reserved_time');
            if ($status !== 'all') {
                $q->where('status', $status);
            }
            $reservations = $q->get();
        }

        return response()->json([
            'status'        => $status,
            'date'          => $date,
            'pending_count' => Reservation::pending()->upcoming()->count(),
            'covers'        => (int) $reservations->where('status', 'confirmed')->sum('party_size'),
            'data'          => $reservations->map(fn (Reservation $r) => $this->row($r, $load[$r->id] ?? null))->all(),
        ]);
    }

    public function approve(Reservation $reservation): JsonResponse
    {
        return response()->json(['ok' => $this->review->approve($reservation, $this->staff())]);
    }

    public function reject(Reservation $reservation): JsonResponse
    {
        return response()->json(['ok' => $this->review->reject($reservation, $this->staff())]);
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

        $waId = WhatsAppClient::normalizeMx($data['phone']);
        $contact = Contact::firstOrCreate(
            ['channel' => Channel::WhatsApp->value, 'external_id' => $waId],
            ['name' => $data['name'], 'wa_id' => $waId],
        );

        $reservation = Reservation::create([
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

        return response()->json(['ok' => true, 'reservation' => $this->row($reservation->load('contact'))]);
    }

    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'date'       => ['required', 'date'],
            'time'       => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $reservation->update([
            'reserved_date' => $data['date'],
            'reserved_time' => $data['time'],
            'party_size'    => (int) $data['party_size'],
            'name'          => $data['name'],
        ]);

        return response()->json(['ok' => true, 'reservation' => $this->row($reservation->load('contact'))]);
    }

    public function cancel(Reservation $reservation): JsonResponse
    {
        $this->cancellation->cancel($reservation);

        return response()->json(['ok' => true]);
    }

    private function row(Reservation $r, ?array $load = null): array
    {
        return [
            'id'                => $r->id,
            'name'              => $r->name,
            'date'              => $r->reserved_date?->toDateString(),
            'time'              => substr((string) $r->reserved_time, 0, 5),
            'party_size'        => (int) $r->party_size,
            'status'            => $r->status,
            'source'            => $r->source,
            'notes'             => $r->notes,
            'reference_contact' => $r->reference_contact,
            'contact'           => $r->contact ? [
                'id'          => $r->contact->id,
                'name'        => $r->contact->name,
                'external_id' => $r->contact->external_id,
            ] : null,
            'load'              => $load,
        ];
    }

    private function staff(): string
    {
        return request()->user()?->email ?? 'staff';
    }
}
