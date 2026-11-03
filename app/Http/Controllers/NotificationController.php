<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Panel notification bell (additive, read-only).
 *
 * The navbar bell polls `GET /notifications/summary` every ~20s, passing the
 * highest message/reservation id it has already seen. This endpoint does PURE
 * DB READS — no schema changes, no writes — and returns how many NEW items have
 * appeared since then, so the browser can show a persistent unread badge and
 * alert (toast + sound) when a new conversation starts or a reservation is made.
 *
 * Signals:
 *   - new_messages     : conversations with new inbound activity since last seen
 *   - new_reservations : reservations created since last seen
 *   - attention        : conversations currently in the `human` state
 *
 * This controller never touches the inbound/webhook/bot pipeline.
 */
class NotificationController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $afterMessage     = (int) $request->query('after_message', 0);
        $afterReservation = (int) $request->query('after_reservation', 0);

        $maxMessageId     = (int) Message::where('direction', Message::DIR_IN)->max('id');
        $maxReservationId = (int) Reservation::max('id');

        // Count NEW items only once the client has a baseline (after_* > 0). On
        // the very first load (0) we report nothing new and the browser just
        // stores the current max as its baseline — no alert for history.
        $newMessages = $afterMessage > 0
            ? (int) Message::where('direction', Message::DIR_IN)
                ->where('id', '>', $afterMessage)
                ->distinct()
                ->count('contact_id')
            : 0;

        $newReservations = $afterReservation > 0
            ? (int) Reservation::where('id', '>', $afterReservation)->count()
            : 0;

        $attentionCount = (int) Conversation::where('state', Conversation::STATE_HUMAN)->count();

        return response()->json([
            'ts'                 => now()->timestamp,
            'max_message_id'     => $maxMessageId,
            'max_reservation_id' => $maxReservationId,
            'new_messages'       => $newMessages,
            'new_reservations'   => $newReservations,
            'attention'          => $attentionCount,
            'recent'             => $this->recent(),
        ]);
    }

    /**
     * A short, mixed feed for the dropdown body — each entry links straight to
     * the conversation. Read-only, capped small.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recent(): array
    {
        $items = [];

        // Latest inbound messages (newest 6 contacts).
        $messages = Message::where('direction', Message::DIR_IN)
            ->with('contact:id,name,external_id,wa_id,channel')
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'contact_id', 'body', 'type', 'created_at']);

        foreach ($messages as $m) {
            $name = $m->contact?->name ?: ($m->contact?->external_id ?? $m->contact?->wa_id ?? 'Cliente');
            $items[] = [
                'kind'       => 'message',
                'icon'       => 'fa-comment-dots',
                'title'      => $name,
                'subtitle'   => $this->preview($m),
                'at'         => $m->created_at?->timestamp,
                'at_label'   => $this->timeLabel($m->created_at),
                'contact_id' => $m->contact_id,
                'url'        => $m->contact_id ? route('conversations.show', $m->contact_id) : route('conversations.index'),
            ];
        }

        // Recent reservations (any status) — each links to its guest's chat.
        $reservations = Reservation::with('contact:id,name,external_id,wa_id')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        foreach ($reservations as $r) {
            $items[] = [
                'kind'       => 'reservation',
                'icon'       => $r->status === Reservation::STATUS_PENDING ? 'fa-calendar-plus' : 'fa-calendar-check',
                'title'      => $r->name ?: ($r->contact?->name ?? 'Reservación'),
                'subtitle'   => $this->reservationLine($r),
                'at'         => $r->created_at?->timestamp,
                'at_label'   => $this->timeLabel($r->created_at),
                'contact_id' => $r->contact_id,
                'url'        => $r->contact_id ? route('conversations.show', $r->contact_id) : route('reservations.index'),
            ];
        }

        // Newest first; cap the combined feed.
        usort($items, fn($a, $b) => ($b['at'] ?? 0) <=> ($a['at'] ?? 0));

        return array_slice($items, 0, 8);
    }

    private function preview(Message $m): string
    {
        if ($m->type !== 'text') {
            return '[' . $m->type . ']';
        }

        $body = trim((string) $m->body);

        return mb_strlen($body) > 60 ? mb_substr($body, 0, 60) . '...' : $body;
    }

    private function reservationLine(Reservation $r): string
    {
        $date  = $r->reserved_date?->translatedFormat('d M');
        $time  = substr((string) $r->reserved_time, 0, 5);
        $label = $r->status === Reservation::STATUS_PENDING ? 'Por autorizar · ' : '';

        return trim("{$label}{$r->party_size}p · {$date} {$time}");
    }

    private function timeLabel(?Carbon $at): string
    {
        if (! $at) {
            return '';
        }

        return $at->isToday() ? $at->format('H:i') : $at->translatedFormat('d M H:i');
    }
}
