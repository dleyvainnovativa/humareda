@extends('layouts.app')
@section('title', 'Conversación')

@push('head')
<style>
    .hp-thread { display: flex; flex-direction: column; gap: .5rem; max-width: 720px; margin: 0 auto; }
    .hp-bubble { max-width: 78%; padding: .55rem .8rem; border-radius: 12px; font-size: .9rem; line-height: 1.35; white-space: pre-wrap; }
    .hp-bubble .hp-meta { font-size: .68rem; opacity: .6; margin-top: .25rem; }
    .hp-in  { align-self: flex-start; background: var(--hp-surface-2); border: 1px solid var(--hp-border); border-bottom-left-radius: 3px; }
    .hp-out { align-self: flex-end; background: rgba(194,65,12,.10); border: 1px solid rgba(194,65,12,.25); border-bottom-right-radius: 3px; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('conversations.index') }}" class="btn btn-icon btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i></a>
    <div>
        <div class="fw-semibold">{{ $contact->name ?? $contact->wa_id }}</div>
        <div class="text-secondary small hp-mono">{{ $contact->wa_id }}</div>
    </div>
    <span class="ms-auto hp-pill {{ ($contact->conversation?->state ?? 'idle') === 'human' ? 'is-human' : 'is-confirmed' }}">
        {{ ($contact->conversation?->state ?? 'idle') === 'human' ? 'Con agente' : 'Bot' }}
    </span>
</div>

<div class="hp-card">
    <div class="hp-card-body">
        @if ($messages->isEmpty())
            <p class="text-secondary small text-center py-4 mb-0">Sin mensajes.</p>
        @else
            <div class="hp-thread">
                @foreach ($messages as $m)
                    <div class="hp-bubble {{ $m->direction === 'in' ? 'hp-in' : 'hp-out' }}">
                        @if ($m->type !== 'text')
                            <em class="text-secondary">[{{ $m->type }}]</em>
                        @endif
                        {{ $m->body }}
                        <div class="hp-meta">
                            {{ $m->direction === 'in' ? 'Cliente' : ($m->sender === 'bot' ? 'Bot' : $m->sender) }}
                            · {{ $m->created_at?->format('d M H:i') }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="text-secondary small text-center mt-4 mb-0">
            <i class="fa-solid fa-lock me-1"></i> Vista de solo lectura. Responder desde el panel llega en la etapa 6.
        </p>
    </div>
</div>
@endsection
