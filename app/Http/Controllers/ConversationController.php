<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live conversation panel (T6, replaces the read-only T2 ConversationLogController).
 *
 * On the live number the Cloud API is the ONLY reply path, so staff answer
 * here. Replying, taking over, and returning to the bot all run through this
 * controller. A lightweight JSON poll keeps the thread fresh without websockets
 * (shared-hosting friendly).
 */
class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'attention'); // attention | all

        $query = Contact::query()->with('conversation')->withCount('messages');

        if ($filter === 'attention') {
            $query->whereHas('conversation', fn($q) => $q->where('state', Conversation::STATE_HUMAN));
        }

        $contacts = $query->orderByDesc('last_seen_at')->paginate(25)->withQueryString();

        $attentionCount = Conversation::where('state', Conversation::STATE_HUMAN)->count();

        return view('conversations.index', compact('contacts', 'filter', 'attentionCount'));
    }

    public function show(Contact $contact): View
    {
        $contact->load('conversation');
        $messages = $contact->messages()->orderBy('id')->limit(300)->get();

        return view('conversations.show', compact('contact', 'messages'));
    }

    /**
     * JSON poll: messages with id greater than ?after=. Keeps the open thread live.
     */
    public function poll(Request $request, Contact $contact): JsonResponse
    {
        $after = (int) $request->query('after', 0);

        $messages = $contact->messages()
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'direction', 'type', 'body', 'sender', 'created_at']);

        return response()->json([
            'state'    => $contact->conversation?->state ?? 'idle',
            'messages' => $messages->map(fn($m) => [
                'id'        => $m->id,
                'direction' => $m->direction,
                'type'      => $m->type,
                'body'      => $m->body,
                'sender'    => $m->sender,
                'at'        => $m->created_at?->format('d M H:i'),
                'time'      => $m->created_at?->format('H:i'),
                'day'       => $this->dayLabel($m),
            ]),
        ]);
    }

    /**
     * Staff sends a message to the guest. Free-form works inside the 24h window;
     * outside it, WhatsApp will reject free-form text (a template would be
     * needed), so we surface that instead of silently failing.
     */
    public function reply(Request $request, Contact $contact, WhatsAppClient $wa): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        $conversation = $contact->conversation;
        $windowOpen   = $conversation?->isWindowOpen() ?? false;

        if (! $windowOpen) {
            return response()->json([
                'ok'      => false,
                'message' => 'La ventana de 24 h está cerrada; WhatsApp no permite texto libre. El cliente debe escribir primero (o usar una plantilla).',
            ], 422);
        }

        $wamid = $wa->sendText($contact->wa_id, $data['body']);
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

        return response()->json([
            'ok'      => true,
            'message' => [
                'id' => $message->id,
                'direction' => 'out',
                'body' => $message->body,
                'sender' => $message->sender,
                'at' => $message->created_at->format('d M H:i'),
                'time' => $message->created_at->format('H:i'),
                'day' => $this->dayLabel($message),
            ],
        ]);
    }

    /** Human-friendly day bucket for grouping in the thread (Hoy / Ayer / d M). */
    private function dayLabel(Message $m): string
    {
        if (! $m->created_at) {
            return '';
        }

        return $m->created_at->isToday() ? 'Hoy'
            : ($m->created_at->isYesterday() ? 'Ayer' : $m->created_at->format('d M'));
    }

    /** Staff grabs a bot-run conversation (no notification). */
    public function takeOver(Contact $contact): JsonResponse
    {
        $conversation = $contact->conversation;
        if ($conversation) {
            $conversation->update(['state' => Conversation::STATE_HUMAN, 'state_changed_at' => now()]);
        }
        return response()->json(['ok' => true, 'state' => 'human']);
    }

    /** Hand control back to the bot; start the next turn fresh. */
    public function returnToBot(Contact $contact): JsonResponse
    {
        $conversation = $contact->conversation;
        if ($conversation) {
            $conversation->update([
                'state'            => Conversation::STATE_IDLE,
                'context'          => ['slots' => ['date' => null, 'time' => null, 'party_size' => null, 'name' => null]],
                'state_changed_at' => now(),
            ]);
        }
        return response()->json(['ok' => true, 'state' => 'idle']);
    }
}
