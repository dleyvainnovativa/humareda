<?php

namespace App\Listeners;

use App\Events\ReservationRequested;
use App\Models\Reservation;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails staff when a reservation request needs authorization. Best-effort: a
 * mail failure is logged, never breaks the guest turn. The durable signal is
 * the panel (status = pending surfaces under "Pendientes").
 *
 * Register (if event discovery is off) in AppServiceProvider::boot():
 *   Event::listen(ReservationRequested::class, NotifyStaffOfRequest::class);
 */
class NotifyStaffOfRequest
{
    public function handle(ReservationRequested $event): void
    {
        $to = Setting::get('staff_notify_email');
        if (! $to) {
            return;
        }

        $r = Reservation::find($event->reservationId);
        if (! $r) {
            return;
        }

        $kind = $event->kind === 'modify' ? 'CAMBIO de reservación' : 'Nueva solicitud de reservación';
        $who  = $event->contactName ? "{$event->contactName} ({$event->waId})" : $event->waId;
        $url  = rtrim((string) config('app.url'), '/') . '/reservations?status=pending';

        $body = "{$kind} — requiere autorización.\n\n"
              . "Cliente: {$who}\n"
              . "Nombre: {$r->name}\n"
              . "Personas: {$r->party_size}\n"
              . "Fecha: {$r->reserved_date?->toDateString()}  Hora: " . substr((string) $r->reserved_time, 0, 5) . "\n"
              . "Referencia: {$r->reference_contact}\n\n"
              . "Autorizar en el panel: {$url}\n";

        try {
            Mail::raw($body, function ($m) use ($to) {
                $m->to($to)->subject('Humareda Prime — reservación por autorizar');
            });
        } catch (\Throwable $e) {
            Log::warning('Request email failed', ['error' => $e->getMessage()]);
        }
    }
}
