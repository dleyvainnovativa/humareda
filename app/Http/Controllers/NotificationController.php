<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * Panel notification bell (additive, read-only).
 *
 * The navbar bell polls `GET /notifications/summary` every ~20s. This endpoint
 * does PURE DB READS — no schema changes, no writes. The browser keeps the
 * "last seen" watermarks in localStorage and decides when something is new, so
 * the server stays stateless and each staff member gets their own unread state.
 *
 * Signals surfaced:
 *   - inbound      : a new inbound WhatsApp/Messenger/IG message arrived
 *   - reservations : a new reservation is pending authorization
 *   - attention    : a conversation is in the `human` state (needs a person)
 *
 * This controller never touches the inbound/webhook/bot pipeline.
 */
class NotificationController extends Controller
{
    public function summary(): JsonResponse
    {
        // --- Monotonic watermarks the client diffs against ------------------
        $maxInboundId    = (int) Message::where('direction', Message::DIR_IN)->max('id');
        $inbound24h      = (int) Message::where('direction', Message::DIR_IN)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $pendingQuery    = Reservation::pending()->upcoming();
        $pendingCount    = (int) (clone $pendingQuery)->count();
        $maxPendingId    = (int) (clone $pendingQuery)->max('id');

        $humanConvs      = Conversation::where('state', Conversation::STATE_HUMAN);
        $attentionCount  = (int) (clone $humanConvs)->count();
        $attentionMaxAt  = (clone $humanConvs)->max('state_changed_at');
        $attentionToken  = $attentionCount . ':' . ($attentionMaxAt ? Carbon::parse($attentionMaxAt)->timestamp : 0);

        return response()->json([
            'ts'      => now()->timestamp,
            'signals' => [
                'inbound'      => ['max_id' => $maxInboundId, 'count_24h' => $inbound24h],
                'reservations' => ['pending' => $pendingCount, 'max_id' => $maxPendingId],
                'attention'    => ['count' => $attentionCount, 'token' => $attentionToken],
            ],
            'recent'  => $this->recent(),
        ]);
    }

    /**
     * A short, mixed feed for the dropdown body. Read-only, capped small.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recent(): array
    {
        $items = [];

        // Latest inbound message per recent contact (deduped to newest 6).
        $messages = Message::where('direction', Message::DIR_IN)
            ->with('contact:id,name,external_id,wa_id,channel')
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'contact_id', 'body', 'type', 'created_at']);

        foreach ($messages as $m) {
            $name = $m->contact?->name ?: ($m->contact?->external_id ?? $m->contact?->wa_id ?? 'Cliente');
            $items[] = [
                'kind'     => 'message',
                'icon'     => 'fa-comment-dots',
                'title'    => $name,
                'subtitle' => $this->preview($m),
                'at'       => $m->created_at?->timestamp,
                'at_label' => $this->timeLabel($m->created_at),
                'url'      => $m->contact ? route('conversations.show', $m->contact_id) : route('conversations.index'),
            ];
        }

        // Pending reservations awaiting authorization.
        $pending = Reservation::pending()->upcoming()
            ->with('contact:id,name,external_id,wa_id')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        foreach ($pending as $r) {
            $items[] = [
                'kind'     => 'reservation',
                'icon'     => 'fa-calendar-plus',
                'title'    => $r->name ?: ($r->contact?->name ?? 'Reservacion'),
                'subtitle' => $this->reservationLine($r),
                'at'       => $r->created_at?->timestamp,
                'at_label' => $this->timeLabel($r->created_at),
                'url'      => route('reservations.index', ['status' => 'pending']),
            ];
        }

        // Newest first; cap the combined feed.
        usort($items, fn ($a, $b) => ($b['at'] ?? 0) <=> ($a['at'] ?? 0));

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
        $date = $r->reserved_date?->translatedFormat('d M');
        $time = substr((string) $r->reserved_time, 0, 5);

        return trim("{$r->party_size}p · {$date} {$time}");
    }

    private function timeLabel(?Carbon $at): string
    {
        if (! $at) {
            return '';
        }

        return $at->isToday() ? $at->format('H:i') : $at->translatedFormat('d M H:i');
    }
}
