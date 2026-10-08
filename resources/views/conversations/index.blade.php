@extends('layouts.app')
@section('title', 'Conversaciones')

@section('content')
<div class="hp-card">
    <div class="hp-card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-semibold mb-0">Conversaciones</h2>
            <span class="text-secondary small">{{ $contacts->total() }} contactos</span>
        </div>

        @if ($contacts->isEmpty())
            <p class="text-secondary small text-center py-4 mb-0">
                <i class="fa-regular fa-comments me-1"></i> Aún no hay mensajes.
            </p>
        @else
            <div class="table-responsive">
                <table class="hp-table">
                    <thead>
                        <tr><th>Contacto</th><th>WhatsApp</th><th>Estado</th><th>Mensajes</th><th>Último</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($contacts as $contact)
                            @php($state = $contact->conversation?->state ?? 'idle')
                            <tr>
                                <td class="fw-medium">{{ $contact->name ?? '—' }}</td>
                                <td class="hp-mono text-secondary">{{ $contact->wa_id }}</td>
                                <td>
                                    <span class="hp-pill {{ $state === 'human' ? 'is-human' : 'is-confirmed' }}">
                                        {{ $state === 'human' ? 'Con agente' : 'Bot' }}
                                    </span>
                                </td>
                                <td class="hp-mono">{{ $contact->messages_count }}</td>
                                <td class="text-secondary small">{{ $contact->last_seen_at?->diffForHumans() }}</td>
                                <td class="text-end">
                                    <a href="{{ route('conversations.show', $contact) }}" class="btn btn-sm btn-outline-secondary">
                                        Ver <i class="fa-solid fa-chevron-right ms-1"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $contacts->links() }}</div>
        @endif
    </div>
</div>
@endsection
