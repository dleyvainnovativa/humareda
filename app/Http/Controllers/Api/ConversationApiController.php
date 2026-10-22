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
 * JSON API for the Flutter staff app (T14 Phase 4) — conversations module.
 *
 * Mirrors the Blade ConversationController's logic (same 24h-window rule, same
 * take-over / return-to-bot state machine) but returns JSON for the app. Auth
 * is the stateless `firebase` bearer guard; the resolved User is on the request.
 *
 * Reply path: on a dedicated Cloud-API number (the backup plan, no WhatsApp app
 * on that line) this is the ONLY way staff answer, so free-form text is gated by
 * the 24h window exactly as in the panel.
 */
class ConversationApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filter  = $request->query('filter', 'attention'); // attention | all
        $channel = $request->query('channel');

        $query = Contact::query()->with('conversation')->withCount('messages');

        if ($filter === 'attention') {
            $query->whereHas('conversation', fn ($q) => $q->where('state', Conversation::STATE_HUMAN));
        }

        if ($channel && in_array($channel, array_column(Channel::cases(), 'value'), true)) {
            $query->where('channel', $channel);
        }

        $contacts = $query->orderByDesc('last_seen_at')->paginate(25)->withQueryString();

        return response()->json([
            'data' => collect($contacts->items())->map(fn (Contact $c) => $this->contactRow($c))->all(),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page'    => $contacts->lastPage(),
                'total'        => $contacts->total(),
            ],
        ]);
    }

    public function show(Contact $contact): JsonResponse
    {
        $contact->load('conversation');
        $messages = $contact->messages()->orderBy('id')->limit(300)->get();

        return response()->json([
            'contact'  => $this->contactRow($contact),
            'window_open' => $contact->conversation?->isWindowOpen() ?? false,
            'messages' => $messages->map(fn (Message $m) => $this->messageRow($m))->all(),
        ]);
    }

    public function poll(Request $request, Contact $contact): JsonResponse
    {
        $after = (int) $request->query('after', 0);

        $messages = $contact->messages()
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json([
            'state'       => $contact->conversation?->state ?? 'idle',
            'window_open' => $contact->conversation?->isWindowOpen() ?? false,
            'messages'    => $messages->map(fn (Message $m) => $this->messageRow($m))->all(),
        ]);
    }

    public function reply(Request $request, Contact $contact, ChannelManager $channels): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        if (! ($contact->conversation?->isWindowOpen() ?? false)) {
            return response()->json([
                'ok'      => false,
                'message' => 'La ventana de 24 h está cerrada; WhatsApp no permite texto libre. El cliente debe escribir primero (o usar una plantilla).',
            ], 422);
        }

        $wamid = $channels->forContact($contact)->sendText($contact->external_id, $data['body']);
        if (! $wamid) {
            return response()->json(['ok' => false, 'message' => 'No se pudo enviar. Revisa el log.'], 502);
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

        return response()->json(['ok' => true, 'message' => $this->messageRow($message)]);
    }

    public function takeOver(Contact $contact): JsonResponse
    {
        $contact->conversation?->update([
            'state'            => Conversation::STATE_HUMAN,
            'state_changed_at' => now(),
        ]);

        return response()->json(['ok' => true, 'state' => 'human']);
    }

    public function returnToBot(Contact $contact): JsonResponse
    {
        $contact->conversation?->update([
            'state'            => Conversation::STATE_IDLE,
            'context'          => ['slots' => ['date' => null, 'time' => null, 'party_size' => null, 'name' => null]],
            'state_changed_at' => now(),
        ]);

        return response()->json(['ok' => true, 'state' => 'idle']);
    }

    private function contactRow(Contact $contact): array
    {
        $last = $contact->messages()->latest('id')->first();

        return [
            'id'          => $contact->id,
            'name'        => $contact->name ?: $contact->external_id,
            'channel'     => $contact->channel instanceof Channel ? $contact->channel->value : $contact->channel,
            'external_id' => $contact->external_id,
            'state'       => $contact->conversation?->state ?? 'idle',
            'messages'    => $contact->messages_count ?? $contact->messages()->count(),
            'last_body'   => $last?->body,
            'last_at'     => $last?->created_at?->toIso8601String(),
            'last_seen'   => $contact->last_seen_at?->toIso8601String(),
        ];
    }

    private function messageRow(Message $m): array
    {
        return [
            'id'        => $m->id,
            'direction' => $m->direction,
            'type'      => $m->type,
            'body'      => $m->body,
            'sender'    => $m->sender,
            'iso'       => $m->created_at?->toIso8601String(),
            'time'      => $m->created_at?->format('H:i'),
            'day'       => $this->dayLabel($m),
        ];
    }

    private function dayLabel(Message $m): string
    {
        if (! $m->created_at) {
            return '';
        }

        return $m->created_at->isToday() ? 'Hoy'
            : ($m->created_at->isYesterday() ? 'Ayer' : $m->created_at->format('d M'));
    }
}
