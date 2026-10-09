@extends('layouts.app')
@section('title', 'Conversación')

@push('head')
<style>
    .hp-thread { display:flex; flex-direction:column; gap:.5rem; max-width:760px; margin:0 auto; }
    .hp-bubble { max-width:78%; padding:.55rem .8rem; border-radius:12px; font-size:.9rem; line-height:1.35; white-space:pre-wrap; }
    .hp-bubble .hp-meta { font-size:.68rem; opacity:.6; margin-top:.25rem; }
    .hp-in  { align-self:flex-start; background:var(--hp-surface-2); border:1px solid var(--hp-border); border-bottom-left-radius:3px; }
    .hp-out { align-self:flex-end; background:rgba(194,65,12,.10); border:1px solid rgba(194,65,12,.25); border-bottom-right-radius:3px; }
    .hp-out.is-bot { background:var(--hp-surface-2); border-color:var(--hp-border); }
    .hp-composer { max-width:760px; margin:.75rem auto 0; }
</style>
@endpush

@section('content')
@php($state = $contact->conversation?->state ?? 'idle')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('conversations.index') }}" class="btn btn-icon btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i></a>
    <div>
        <div class="fw-semibold">{{ $contact->name ?? $contact->wa_id }}</div>
        <div class="text-secondary small hp-mono">{{ $contact->wa_id }}</div>
    </div>
    <span id="hp-state" class="ms-auto hp-pill {{ $state === 'human' ? 'is-human' : 'is-confirmed' }}">
        {{ $state === 'human' ? 'Con agente' : 'Bot' }}
    </span>
    <button id="hp-takeover" class="btn btn-sm btn-outline-secondary {{ $state === 'human' ? 'd-none' : '' }}">
        <i class="fa-solid fa-hand me-1"></i>Tomar
    </button>
    <button id="hp-return" class="btn btn-sm btn-outline-secondary {{ $state === 'human' ? '' : 'd-none' }}">
        <i class="fa-solid fa-robot me-1"></i>Regresar al bot
    </button>
</div>

<div class="hp-card">
    <div class="hp-card-body">
        <div class="hp-thread" id="hp-thread">
            @foreach ($messages as $m)
                <div class="hp-bubble {{ $m->direction === 'in' ? 'hp-in' : 'hp-out '.($m->sender === 'bot' ? 'is-bot' : '') }}">
                    @if ($m->type !== 'text')<em class="text-secondary">[{{ $m->type }}]</em> @endif
                    {{ $m->body }}
                    <div class="hp-meta">
                        {{ $m->direction === 'in' ? 'Cliente' : ($m->sender === 'bot' ? 'Bot' : $m->sender) }} · {{ $m->created_at?->format('d M H:i') }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<form class="hp-composer" id="hp-composer">
    <div class="input-group">
        <input type="text" class="form-control" id="hp-reply" placeholder="Escribe una respuesta…" autocomplete="off" maxlength="4000">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i></button>
    </div>
    <div class="form-text" id="hp-hint">Respondes como agente; la conversación pasa a modo manual.</div>
</form>
@endsection

@push('scripts')
<script>
// Run after the deferred Vite module (app.js) has executed and defined
// window.HP. A plain IIFE here runs during parse — before app.js — so HP
// would be undefined. DOMContentLoaded fires after deferred modules run.
document.addEventListener('DOMContentLoaded', function () {
    const contactId = @json($contact->id);
    const urls = {
        poll:       @json(route('conversations.poll', $contact)),
        reply:      @json(route('conversations.reply', $contact)),
        takeover:   @json(route('conversations.takeover', $contact)),
        returnBot:  @json(route('conversations.return', $contact)),
    };
    const thread = document.getElementById('hp-thread');
    const statePill = document.getElementById('hp-state');
    let lastId = {{ $messages->last()->id ?? 0 }};

    const esc = (s) => (s ?? '').replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    const atBottom = () => (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 120);

    function addBubble(m) {
        const div = document.createElement('div');
        const bot = m.sender === 'bot';
        div.className = 'hp-bubble ' + (m.direction === 'in' ? 'hp-in' : 'hp-out ' + (bot ? 'is-bot' : ''));
        const who = m.direction === 'in' ? 'Cliente' : (bot ? 'Bot' : m.sender);
        const tag = m.type && m.type !== 'text' ? `<em class="text-secondary">[${esc(m.type)}]</em> ` : '';
        div.innerHTML = `${tag}${esc(m.body)}<div class="hp-meta">${esc(who)} · ${esc(m.at)}</div>`;
        thread.appendChild(div);
    }

    function setState(state) {
        const human = state === 'human';
        statePill.textContent = human ? 'Con agente' : 'Bot';
        statePill.className = 'ms-auto hp-pill ' + (human ? 'is-human' : 'is-confirmed');
        document.getElementById('hp-takeover').classList.toggle('d-none', human);
        document.getElementById('hp-return').classList.toggle('d-none', !human);
    }

    async function poll() {
        try {
            const data = await HP.http.get(`${urls.poll}?after=${lastId}`);
            const stick = atBottom();
            (data.messages || []).forEach(m => { addBubble(m); lastId = Math.max(lastId, m.id); });
            setState(data.state);
            if (stick && data.messages.length) window.scrollTo(0, document.body.scrollHeight);
        } catch (e) { /* transient; keep polling */ }
    }
    setInterval(poll, 4000);

    document.getElementById('hp-composer').addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('hp-reply');
        const body = input.value.trim();
        if (!body) return;
        const btn = e.target.querySelector('button[type=submit]');
        HP.setLoading(btn, true);
        try {
            const res = await HP.http.post(urls.reply, { body });
            if (res.ok) { addBubble(res.message); lastId = Math.max(lastId, res.message.id); input.value = ''; window.scrollTo(0, document.body.scrollHeight); setState('human'); }
        } catch (err) {
            HP.toast(err?.data?.message || 'No se pudo enviar.', 'error', 6000);
        } finally { HP.setLoading(btn, false); }
    });

    document.getElementById('hp-takeover').addEventListener('click', async () => {
        try { await HP.http.post(urls.takeover, {}); setState('human'); HP.toast('Conversación tomada.', 'success'); } catch (e) { HP.toast('Error.', 'error'); }
    });
    document.getElementById('hp-return').addEventListener('click', async () => {
        try { await HP.http.post(urls.returnBot, {}); setState('idle'); HP.toast('Devuelta al bot.', 'success'); } catch (e) { HP.toast('Error.', 'error'); }
    });
});
</script>
@endpush