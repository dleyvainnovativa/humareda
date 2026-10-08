@extends('layouts.app')
@section('title', 'Panel')

@section('content')
@php($cards = [
    ['label' => 'Por autorizar', 'value' => $stats['pending'],      'icon' => 'fa-bell',           'href' => route('reservations.index', ['status'=>'pending']), 'alert' => $stats['pending'] > 0],
    ['label' => 'Comensales hoy','value' => $stats['today_covers'], 'icon' => 'fa-users',          'href' => null, 'alert' => false],
    ['label' => 'Próximas',      'value' => $stats['upcoming'],     'icon' => 'fa-calendar-check', 'href' => null, 'alert' => false],
    ['label' => 'Contactos',     'value' => $stats['contacts'],     'icon' => 'fa-address-book',   'href' => null, 'alert' => false],
])

<div class="row g-3 mb-4">
    @foreach ($cards as $c)
        <div class="col-6 col-xl-3">
            <a href="{{ $c['href'] ?? '#' }}" class="text-decoration-none">
                <div class="hp-card h-100" @if($c['alert']) style="border-color:var(--hp-ember);" @endif><div class="hp-card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="hp-metric">
                            <span class="hp-metric-label">{{ $c['label'] }}</span>
                            <span class="hp-metric-value" @if($c['alert']) style="color:var(--hp-primary);" @endif>{{ $c['value'] }}</span>
                        </div>
                        <span class="hp-metric-icon"><i class="fa-solid {{ $c['icon'] }}"></i></span>
                    </div>
                </div></div>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="hp-card h-100"><div class="hp-card-body">
            <h2 class="h6 fw-semibold mb-3">Comensales — próximos 7 días</h2>
            <div class="d-flex align-items-end gap-2" style="height:160px;">
                @foreach ($outlook as $o)
                    @php($h = (int) round(($o['covers'] / $max) * 130))
                    <div class="d-flex flex-column align-items-center justify-content-end flex-fill" style="height:100%;">
                        <span class="hp-mono small mb-1">{{ $o['covers'] }}</span>
                        <div style="width:100%;max-width:34px;height:{{ max($h, 3) }}px;border-radius:6px 6px 0 0;
                                    background:linear-gradient(180deg,var(--hp-ember),var(--hp-gold));"></div>
                        <span class="text-secondary mt-1" style="font-size:.7rem;text-transform:capitalize;">{{ $o['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div></div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="hp-card h-100"><div class="hp-card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 fw-semibold mb-0">Confirmadas de hoy</h2>
                <a href="{{ route('reservations.index') }}" class="btn btn-sm btn-outline-secondary">Ver todas</a>
            </div>
            @if ($todayList->isEmpty())
                <p class="text-secondary small mb-0 py-4 text-center"><i class="fa-regular fa-calendar me-1"></i> Sin reservaciones confirmadas para hoy.</p>
            @else
                <div class="table-responsive">
                    <table class="hp-table">
                        <thead><tr><th>Hora</th><th>Nombre</th><th>Personas</th><th>Contacto</th></tr></thead>
                        <tbody>
                            @foreach ($todayList as $r)
                                <tr>
                                    <td class="hp-mono">{{ \Illuminate\Support\Carbon::parse($r->reserved_time)->format('H:i') }}</td>
                                    <td>{{ $r->name }}</td>
                                    <td class="hp-mono">{{ $r->party_size }}</td>
                                    <td class="text-secondary">{{ $r->contact?->wa_id }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div></div>
    </div>
</div>
@endsection
