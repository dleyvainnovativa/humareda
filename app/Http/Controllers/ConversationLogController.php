<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Message;
use Illuminate\Contracts\View\View;

/**
 * Read-only message log for T2 (proves the pipe + gives staff visibility).
 * T6 upgrades this into a live conversation panel with reply + handoff controls.
 */
class ConversationLogController extends Controller
{
    public function index(): View
    {
        $contacts = Contact::query()
            ->with(['conversation'])
            ->withCount('messages')
            ->orderByDesc('last_seen_at')
            ->paginate(25);

        return view('conversations.index', compact('contacts'));
    }

    public function show(Contact $contact): View
    {
        $contact->load('conversation');

        $messages = $contact->messages()
            ->orderBy('created_at')
            ->limit(300)
            ->get();

        return view('conversations.show', compact('contact', 'messages'));
    }
}
