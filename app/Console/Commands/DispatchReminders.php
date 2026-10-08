<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\Reservation;
use App\Models\ReservationReminder;
use App\Services\Reminders\ReminderMessage;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends due same-day reminders. Run every minute via the scheduler:
 *   $schedule->command('reminders:dispatch')->everyMinute()->withoutOverlapping();
 *
 * Channel choice per reminder:
 *   - guest messaged within 24h (window open) -> free-form text (free)
 *   - otherwise                               -> approved utility template
 *
 * The reminder asks the guest to reply CONFIRMO / CANCELO (handled on the
 * inbound side by ReminderReplyHandler).
 */
class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch {--limit=100}';
    protected $description = 'Send due same-day reservation reminders.';

    public function handle(WhatsAppClient $wa): int
    {
        $due = ReservationReminder::query()
            ->where('status', ReservationReminder::STATUS_PENDING)
            ->where('scheduled_at', '<=', now())
            ->with('reservation.contact.conversation')
            ->orderBy('scheduled_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $sent = 0;
        foreach ($due as $reminder) {
            $reservation = $reminder->reservation;

            // Reservation gone or no longer confirmed -> drop the reminder.
            if (! $reservation || $reservation->status !== Reservation::STATUS_CONFIRMED) {
                $reminder->update(['status' => ReservationReminder::STATUS_CANCELLED]);
                continue;
            }

            $contact = $reservation->contact;
            $lang    = $contact->locale ?: 'es';
            $time    = substr((string) $reservation->reserved_time, 0, 5);
            $party   = (int) $reservation->party_size;
            $name    = $contact->name ?: $reservation->name;

            $windowOpen = $reservation->contact->conversation?->isWindowOpen() ?? false;

            if ($windowOpen) {
                $wamid   = $wa->sendText($contact->wa_id, ReminderMessage::text($lang, $name, $time, $party));
                $channel = 'free_form';
            } else {
                $wamid = $wa->sendTemplate(
                    $contact->wa_id,
                    (string) config('services.whatsapp.reminder_template', 'reminder_same_day'),
                    ReminderMessage::langCode($lang),
                    ReminderMessage::templateComponents($name, $time, $party),
                );
                $channel = 'template';
            }

            if ($wamid) {
                $reminder->update([
                    'status'  => ReservationReminder::STATUS_SENT,
                    'channel' => $channel,
                    'sent_at' => now(),
                ]);
                Message::create([
                    'contact_id'    => $contact->id,
                    'direction'     => Message::DIR_OUT,
                    'type'          => $channel === 'template' ? 'template' : 'text',
                    'body'          => ReminderMessage::text($lang, $name, $time, $party),
                    'wa_message_id' => $wamid,
                    'sender'        => 'bot',
                    'created_at'    => now(),
                ]);
                $sent++;
            } else {
                $reminder->update([
                    'status' => ReservationReminder::STATUS_FAILED,
                    'error'  => "send failed ({$channel})",
                ]);
            }
        }

        $this->info("Reminders due: {$due->count()}, sent: {$sent}");
        return self::SUCCESS;
    }
}
