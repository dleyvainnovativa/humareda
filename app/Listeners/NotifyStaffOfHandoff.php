<?php

namespace App\Listeners;

use App\Events\ConversationHandedOff;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails staff when a conversation is handed off. Best-effort: a mail failure
 * is logged but never breaks the guest's turn (this runs inside the
 * dispatchAfterResponse job). The panel is the durable signal — state=human
 * surfaces the conversation under "Requieren atención" regardless of email.
 *
 * Laravel 11+ auto-discovers this listener by the handle() type-hint. If your
 * project disabled discovery, register it in a service provider (see README).
 */
class NotifyStaffOfHandoff
{
    public function handle(ConversationHandedOff $event): void
    {
        $to = Setting::get('staff_notify_email');
        if (! $to) {
            return;
        }

        $reason = match ($event->reason) {
            'big_party' => 'grupo grande',
            'uncovered' => 'consulta fuera de cobertura',
            default     => 'el cliente pidió hablar con una persona',
        };

        $who  = $event->contactName ? "{$event->contactName} ({$event->waId})" : $event->waId;
        $url  = rtrim((string) config('app.url'), '/') . "/conversations/{$event->contactId}";
        $body = "Una conversación de WhatsApp necesita atención.\n\n"
              . "Cliente: {$who}\nMotivo: {$reason}\n\nAbrir en el panel: {$url}\n";

        try {
            Mail::raw($body, function ($m) use ($to) {
                $m->to($to)->subject('Humareda Prime — conversación requiere atención');
            });
        } catch (\Throwable $e) {
            Log::warning('Handoff email failed', ['error' => $e->getMessage()]);
        }
    }
}
