<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\Reservation;
use App\Models\Setting;
use App\Services\Push\FcmPusher;
use Illuminate\Console\Command;

/**
 * Cron-driven FCM fan-out for the staff mobile app.
 *
 * This is the shared-hosting-friendly, ADDITIVE alternative to hooking the
 * inbound pipeline: instead of firing a push from inside ProcessInboundMessage
 * (which is off-limits), we scan the DB once a minute for anything new since the
 * last run and notify registered devices. Watermarks live in the `settings`
 * table, so there is no schema change for this.
 *
 * Register alongside reminders:dispatch in bootstrap/app.php:
 *   $schedule->command('push:scan')->everyMinute()->withoutOverlapping();
 */
class PushScan extends Command
{
    protected $signature = 'push:scan';

    protected $description = 'Send FCM notifications for new inbound messages and pending reservations.';

    public function handle(FcmPusher $pusher): int
    {
        // On the very first run, don't blast the whole backlog — just set the
        // watermarks to "now" and start notifying from the next new event.
        if (! Setting::get('push_initialized', false)) {
            Setting::put('push_last_message_id', (int) Message::max('id'), 'int');
            Setting::put('push_last_reservation_id', (int) Reservation::max('id'), 'int');
            Setting::put('push_initialized', true, 'bool');
            $this->info('push:scan initialized watermarks; no backlog sent.');
            return self::SUCCESS;
        }

        $sent = 0;
        $sent += $this->scanMessages($pusher);
        $sent += $this->scanReservations($pusher);

        $this->info("push:scan sent {$sent} notification(s).");

        return self::SUCCESS;
    }

    private function scanMessages(FcmPusher $pusher): int
    {
        $lastId = (int) Setting::get('push_last_message_id', 0);

        $new = Message::where('direction', Message::DIR_IN)
            ->where('id', '>', $lastId)
            ->with('contact:id,name,external_id,wa_id')
            ->orderBy('id')
            ->get();

        if ($new->isEmpty()) {
            return 0;
        }

        // Advance the watermark up front so a mid-run failure never double-sends.
        Setting::put('push_last_message_id', (int) $new->max('id'), 'int');

        // Collapse to the latest message per contact to avoid spamming.
        $byContact = $new->groupBy('contact_id');
        $count     = 0;

        if ($byContact->count() > 5) {
            $pusher->notifyAll(
                'Nuevos mensajes',
                $new->count() . ' mensajes nuevos de ' . $byContact->count() . ' chats',
                ['type' => 'message'],
            );
            return 1;
        }

        foreach ($byContact as $contactId => $messages) {
            $latest  = $messages->last();
            $contact = $latest->contact;
            $name    = $contact?->name ?: ($contact?->external_id ?? $contact?->wa_id ?? 'Cliente');

            $pusher->notifyAll(
                'Mensaje de ' . $name,
                $this->preview($latest),
                ['type' => 'message', 'contact_id' => (string) $contactId],
            );
            $count++;
        }

        return $count;
    }

    private function scanReservations(FcmPusher $pusher): int
    {
        $lastId = (int) Setting::get('push_last_reservation_id', 0);

        $new = Reservation::where('id', '>', $lastId)->max('id');
        // Advance watermark to the newest reservation id regardless of status.
        if ($new) {
            Setting::put('push_last_reservation_id', (int) $new, 'int');
        }

        $pending = Reservation::pending()
            ->where('id', '>', $lastId)
            ->with('contact:id,name,external_id,wa_id')
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return 0;
        }

        if ($pending->count() > 3) {
            $pusher->notifyAll(
                'Reservaciones por autorizar',
                $pending->count() . ' nuevas reservaciones pendientes',
                ['type' => 'reservation'],
            );
            return 1;
        }

        $count = 0;
        foreach ($pending as $r) {
            $who = $r->name ?: ($r->contact?->name ?? 'Cliente');
            $pusher->notifyAll(
                'Reservación por autorizar',
                $who . ' · ' . $r->party_size . 'p · ' . $r->reserved_date?->format('d M') . ' ' . substr((string) $r->reserved_time, 0, 5),
                ['type' => 'reservation', 'reservation_id' => (string) $r->id],
            );
            $count++;
        }

        return $count;
    }

    private function preview(Message $m): string
    {
        if ($m->type !== 'text') {
            return '[' . $m->type . ']';
        }

        $body = trim((string) $m->body);

        return mb_strlen($body) > 80 ? mb_substr($body, 0, 80) . '...' : $body;
    }
}
