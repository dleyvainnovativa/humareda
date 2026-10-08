@extends('layouts.app')
@section('title', 'Panel')

@section('content')
@php($cards = [
    ['label' => 'Reservaciones hoy', 'value' => $stats['today_reservations'], 'icon' => 'fa-calendar-check'],
    ['label' => 'Comensales hoy',    'value' => $stats['today_covers'],       'icon' => 'fa-users'],
    ['label' => 'Próximas',          'value' => $stats['upcoming'],           'icon' => 'fa-clock'],
    ['label' => 'Contactos',         'value' => $stats['contacts'],           'icon' => 'fa-address-book'],
])

<div class="row g-3 mb-4">
    @foreach ($cards as $c)
        <div class="col-6 col-xl-3">
            <div class="hp-card h-100">
                <div class="hp-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="hp-metric">
                            <span class="hp-metric-label">{{ $c['label'] }}</span>
                            <span class="hp-metric-value">{{ $c['value'] }}</span>
                        </div>
                        <span class="hp-metric-icon"><i class="fa-solid {{ $c['icon'] }}"></i></span>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="hp-card">
    <div class="hp-card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 fw-semibold mb-0">Reservaciones de hoy</h2>
            <span class="hp-pill is-confirmed">{{ now()->translatedFormat('l d M') }}</span>
        </div>

        @if ($todayList->isEmpty())
            <p class="text-secondary small mb-0 py-4 text-center">
                <i class="fa-regular fa-calendar me-1"></i> Sin reservaciones para hoy.
            </p>
        @else
            <div class="table-responsive">
                <table class="hp-table">
                    <thead>
                        <tr><th>Hora</th><th>Nombre</th><th>Personas</th><th>Contacto</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($todayList as $r)
                            <tr>
                                <td class="hp-mono">{{ \Illuminate\Support\Carbon::parse($r->reserved_time)->format('H:i') }}</td>
                                <td>{{ $r->name }}</td>
                                <td class="hp-mono">{{ $r->party_size }}</td>
                                <td class="text-secondary">{{ $r->contact?->wa_id }}</td>
                                <td><span class="hp-pill is-confirmed">Confirmada</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
