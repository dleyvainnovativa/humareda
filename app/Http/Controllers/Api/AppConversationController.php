<?php

namespace App\Http\Controllers\Api;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Channels\ChannelManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile app — conversation inbox + thread + reply.
 *
 * Read endpoints mirror the web panel's data; `reply` reuses the EXACT send path
 * the panel uses (ChannelManager + the 24h customer-service window guard), so the
 * phone and the desktop behave identically and nothing in the bot pipeline moves.
 */
class AppConversationController extends Controller
{
    /** Inbox: contacts with their latest message, newest activity first. */
    public function index(Request $request): JsonResponse
    {
        $filter  = $request->query('filter', 'all');   // all | attention
        $channel = $request->query('channel');         // whatsapp | messenger | instagram | null

        $query = Contact::query()->with('conversation');

        if ($filter === 'attention') {
            $query->whereHas('conversation', fn ($q) => $q->where('state', Conversation::STATE_HUMAN));
        }

        if ($channel && in_array($channel, array_column(Channel::cases(), 'value'), true)) {
            $query->where('channel', $channel);
        }

        $contacts = $query->orderByDesc('last_seen_at')->paginate(30);

        // Latest message per contact in two queries (no N+1).
        $ids = $contacts->pluck('id')->all();
        $lastIds = Message::whereIn('contact_id', $ids)
            ->selectRaw('MAX(id) as id')
            ->groupBy('contact_id')
            ->pluck('id');
        $lastMessages = Message::whereIn('id', $lastIds)->get()->keyBy('contact_id');

        $data = $contacts->getCollection()->map(function (Contact $c) use ($lastMessages) {
            $last  = $lastMessages->get($c->id);
            $state = $c->conversation?->state ?? Conversation::STATE_IDLE;

            return [
                'id'           => $c->id,
                'name'         => $c->name ?: ($c->external_id ?? $c->wa_id),
                'channel'      => $c->channel?->value ?? 'whatsapp',
                'channel_icon' => $c->channel?->faIcon() ?? 'fa-whatsapp',
                'state'        => $state,
                'needs_human'  => $state === Conversation::STATE_HUMAN,
                'last_message' => $last ? [
                    'body'      => $this->preview($last),
                    'direction' => $last->direction,
                    'at'        => $last->created_at?->toIso8601String(),
                ] : null,
                'last_seen_at' => $c->last_seen_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data'      => $data,
            'next_page' => $contacts->hasMorePages() ? $contacts->currentPage() + 1 : null,
        ]);
    }

    /**
     * Thread. Modes:
     *   default      -> latest page of messages (ascending for display)
     *   ?after=ID    -> only messages newer than ID (polling the open chat)
     *   ?before=ID   -> the page of messages older than ID (scroll-back)
     */
    public function messages(Request $request, Contact $contact): JsonResponse
    {
        $contact->load('conversation');
        $after  = (int) $request->query('after', 0);
        $before = (int) $request->query('before', 0);
        $limit  = 40;

        if ($after > 0) {
            $messages = $contact->messages()
                ->where('id', '>', $after)
                ->orderBy('id')
                ->limit(100)
                ->get();
        } elseif ($before > 0) {
            $messages = $contact->messages()
                ->where('id', '<', $before)
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
        } else {
            $messages = $contact->messages()
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
        }

        $conversation = $contact->conversation;

        return response()->json([
            'contact' => [
                'id'      => $contact->id,
                'name'    => $contact->name ?: ($contact->external_id ?? $contact->wa_id),
                'channel' => $contact->channel?->value ?? 'whatsapp',
                'state'   => $conversation?->state ?? Conversation::STATE_IDLE,
            ],
            'window_open' => $conversation?->isWindowOpen() ?? false,
            'messages'    => $messages->map(fn (Message $m) => $this->serialize($m))->values(),
        ]);
    }

    /**
     * Staff replies from the phone. Identical rules to the panel: free-form text
     * only works inside WhatsApp's 24h window; outside it we surface that instead
     * of silently failing.
     */
    public function reply(Request $request, Contact $contact, ChannelManager $channels): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        $conversation = $contact->conversation;
        $windowOpen   = $conversation?->isWindowOpen() ?? false;

        if (! $windowOpen) {
            return response()->json([
                'ok'      => false,
                'message' => 'La ventana de 24 h está cerrada; WhatsApp no permite texto libre. El cliente debe escribir primero.',
            ], 422);
        }

        $wamid = $channels->forContact($contact)->sendText($contact->external_id, $data['body']);

        if (! $wamid) {
            return response()->json(['ok' => false, 'message' => 'No se pudo enviar.'], 502);
        }

        $message = Message::create([
            'contact_id'    => $contact->id,
            'direction'     => Message::DIR_OUT,
            'type'          => 'text',
            'body'          => $data['body'],
            'wa_message_id' => $wamid,
            'sender'        => $request->user()->email,
            'created_at'    => now(),
        ]);

        return response()->json([
            'ok'      => true,
            'message' => $this->serialize($message),
        ]);
    }

    private function serialize(Message $m): array
    {
        return [
            'id'        => $m->id,
            'direction' => $m->direction,
            'type'      => $m->type,
            'body'      => $m->body,
            'sender'    => $m->sender,
            'at'        => $m->created_at?->toIso8601String(),
        ];
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
