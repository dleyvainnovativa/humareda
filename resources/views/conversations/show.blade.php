@extends('layouts.app')
@section('title', 'Conversación')

@section('content')
@php($state = $contact->conversation?->state ?? 'idle')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('conversations.index') }}" class="btn btn-icon btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i></a>
    <div>
        <div class="fw-semibold">{{ $contact->name ?? $contact->wa_id }}</div>
        <div class="text-secondary small hp-mono">{{ $contact->wa_id }}</div>
    </div>
    <span id="hp-state" class="ms-auto hp-pill {{ $state === 'human' ? 'is-human' : 'is-confirmed' }}">
        <i class="fa-solid {{ $state === 'human' ? 'fa-headset' : 'fa-robot' }}" data-state-icon></i>
        <span data-state-text>{{ $state === 'human' ? 'Con agente' : 'Bot' }}</span>
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
            @php($prevKey = null)
            @php($prevDay = null)
            @foreach ($messages as $m)
                @php($key = $m->direction === 'in' ? 'client' : ($m->sender === 'bot' ? 'bot' : 'agent'))
                @php($side = $m->direction === 'in' ? 'in' : 'out')
                @php($day = $m->created_at ? ($m->created_at->isToday() ? 'Hoy' : ($m->created_at->isYesterday() ? 'Ayer' : $m->created_at->format('d M'))) : '')
                @if ($day && $day !== $prevDay)
                    <div class="hp-day">{{ $day }}</div>
                    @php($prevKey = null)
                @endif
                <div class="hp-row hp-{{ $side }} {{ $key === 'bot' ? 'is-bot' : ($key === 'agent' ? 'is-agent' : '') }} {{ $key === $prevKey ? 'is-cont' : '' }}">
                    <div class="hp-lbl">
                        @if ($key === 'client'){{ $contact->name ?? 'Cliente' }}
                        @elseif ($key === 'bot')<i class="fa-solid fa-robot"></i> Bot
                        @else<i class="fa-solid fa-headset"></i> {{ \Illuminate\Support\Str::before($m->sender, '@') ?: 'Agente' }}
                        @endif
                    </div>
                    <div class="hp-bubble">@if ($m->type !== 'text')<span class="hp-tag">[{{ $m->type }}]</span>@endif{{ $m->body }}<span class="hp-t">{{ $m->created_at?->format('H:i') }}</span></div>
                </div>
                @php($prevKey = $key)
                @php($prevDay = $day)
            @endforeach
        </div>
    </div>
</div>

<form class="hp-composer" id="hp-composer">
    <div class="hp-composer-row">
        <input type="text" class="form-control" id="hp-reply" placeholder="Escribe una respuesta…" autocomplete="off" maxlength="4000">
        <button class="hp-send" type="submit" aria-label="Enviar"><i class="fa-solid fa-paper-plane"></i></button>
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
    const contactName = @json($contact->name ?? 'Cliente');
    const urls = {
        poll:       @json(route('conversations.poll', $contact)),
        reply:      @json(route('conversations.reply', $contact)),
        takeover:   @json(route('conversations.takeover', $contact)),
        returnBot:  @json(route('conversations.return', $contact)),
    };
    const thread = document.getElementById('hp-thread');
    const statePill = document.getElementById('hp-state');
    let lastId = {{ $messages->last()->id ?? 0 }};

    // Grouping state, seeded from the last server-rendered message so the first
    // polled bubble continues (or breaks) the group correctly.
    @php($lastMsg = $messages->last())
    let lastKey = @json($lastMsg ? ($lastMsg->direction === 'in' ? 'client' : ($lastMsg->sender === 'bot' ? 'bot' : 'agent')) : null);
    let lastDay = @json($lastMsg && $lastMsg->created_at ? ($lastMsg->created_at->isToday() ? 'Hoy' : ($lastMsg->created_at->isYesterday() ? 'Ayer' : $lastMsg->created_at->format('d M'))) : null);

    const esc = (s) => (s ?? '').replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
    const atBottom = () => (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 120);
    const keyFor = (m) => m.direction === 'in' ? 'client' : (m.sender === 'bot' ? 'bot' : 'agent');

    function addBubble(m) {
        const key  = keyFor(m);
        const side = m.direction === 'in' ? 'in' : 'out';
        const day  = m.day || '';

        if (day && day !== lastDay) {
            const chip = document.createElement('div');
            chip.className = 'hp-day';
            chip.textContent = day;
            thread.appendChild(chip);
            lastDay = day;
            lastKey = null; // a day break always starts a fresh group
        }

        const cont = key === lastKey;
        const row = document.createElement('div');
        row.className = 'hp-row hp-' + side
            + (key === 'bot' ? ' is-bot' : key === 'agent' ? ' is-agent' : '')
            + (cont ? ' is-cont' : '');

        let label;
        if (key === 'client')    label = esc(contactName);
        else if (key === 'bot')  label = '<i class="fa-solid fa-robot"></i> Bot';
        else                     label = '<i class="fa-solid fa-headset"></i> ' + esc((m.sender || '').split('@')[0] || 'Agente');

        const tag  = m.type && m.type !== 'text' ? `<span class="hp-tag">[${esc(m.type)}]</span>` : '';
        const time = esc(m.time || m.at || '');
        row.innerHTML = `<div class="hp-lbl">${label}</div><div class="hp-bubble">${tag}${esc(m.body)}<span class="hp-t">${time}</span></div>`;
        thread.appendChild(row);
        lastKey = key;
    }

    function setState(state) {
        const human = state === 'human';
        statePill.className = 'ms-auto hp-pill ' + (human ? 'is-human' : 'is-confirmed');
        statePill.querySelector('[data-state-icon]').className = 'fa-solid ' + (human ? 'fa-headset' : 'fa-robot');
        statePill.querySelector('[data-state-text]').textContent = human ? 'Con agente' : 'Bot';
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
    window.scrollTo(0, document.body.scrollHeight);

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