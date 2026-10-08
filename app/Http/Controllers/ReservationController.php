<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Reservation;
use App\Services\Availability\BookingService;
use App\Services\Reservations\CancellationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Staff reservations management. Create/modify go through the same
 * availability-locked BookingService the bot uses, so cover accounting stays
 * correct no matter who books. Cancel uses CancellationService (frees covers +
 * cancels reminders).
 */
class ReservationController extends Controller
{
    public function __construct(
        private BookingService $booking,
        private CancellationService $cancellation,
    ) {}

    public function index(Request $request): View
    {
        $date   = $request->query('date', now()->toDateString());
        $status = $request->query('status', 'confirmed');

        $query = Reservation::with('contact')
            ->whereDate('reserved_date', $date)
            ->orderBy('reserved_time');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $reservations = $query->get();
        $covers = (int) $reservations->where('status', 'confirmed')->sum('party_size');

        return view('reservations.index', compact('reservations', 'date', 'status', 'covers'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone'      => ['required', 'string', 'max:20'],
            'name'       => ['required', 'string', 'max:120'],
            'date'       => ['required', 'date'],
            'time'       => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:200'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        $contact = Contact::firstOrCreate(
            ['wa_id' => preg_replace('/\D/', '', $data['phone'])],
            ['name' => $data['name']],
        );

        $outcome = $this->booking->book(
            $contact->id, $data['date'], $data['time'], (int) $data['party_size'],
            $data['name'], $data['notes'] ?? null, 'staff',
        );

        if ($outcome['reservation']) {
            return response()->json(['ok' => true]);
        }

        return response()->json([
            'ok'      => false,
            'status'  => $outcome['result']->status,
            'message' => $this->statusMessage($outcome['result']->status, $outcome['result']->alternatives),
        ], 422);
    }

    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'date'       => ['required', 'date'],
            'time'       => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $outcome = $this->booking->modify($reservation, $data['date'], $data['time'], (int) $data['party_size'], $data['name']);

        if ($outcome['reservation']) {
            return response()->json(['ok' => true]);
        }
        return response()->json([
            'ok'      => false,
            'message' => $this->statusMessage($outcome['result']->status, $outcome['result']->alternatives),
        ], 422);
    }

    public function cancel(Reservation $reservation): JsonResponse
    {
        $this->cancellation->cancel($reservation);
        return response()->json(['ok' => true]);
    }

    private function statusMessage(string $status, array $alts = []): string
    {
        return match ($status) {
            'full'          => $alts ? 'Lleno. Horarios cercanos: ' . implode(', ', $alts) : 'Sin disponibilidad en ese horario.',
            'outside_hours' => 'Fuera del horario de servicio.' . ($alts ? ' Cercanos: ' . implode(', ', $alts) : ''),
            'closed'        => 'Cerrado ese día.',
            'needs_human'   => 'Grupo grande: requiere revisión manual (ajusta el cupo máximo si procede).',
            'invalid'       => 'Datos inválidos (revisa fecha/personas).',
            default         => 'No se pudo completar.',
        };
    }
}
