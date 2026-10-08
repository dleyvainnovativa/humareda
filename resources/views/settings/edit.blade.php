@extends('layouts.app')
@section('title', 'Ajustes')

@section('content')
@if (session('status'))
    <div class="alert alert-success py-2 px-3 small">{{ session('status') }}</div>
@endif

<div class="hp-card mb-4"><div class="hp-card-body">
    <h2 class="h6 fw-semibold mb-1">Horario y cupos por día</h2>
    <p class="text-secondary small mb-3">
        <i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>
        Estos valores controlan la disponibilidad del bot. Confirma con el cliente los cupos reales
        (sustituyen los valores de ejemplo). <strong>Última reserva</strong> = la hora más tarde en que
        se puede iniciar una mesa; <strong>cupo/slot</strong> = comensales simultáneos por franja;
        <strong>auto-confirma ≤</strong> = tamaño de grupo que el bot confirma solo (arriba pasa con una persona).
    </p>

    <form method="POST" action="{{ route('settings.hours') }}">
        @csrf
        <div class="table-responsive">
            <table class="hp-table align-middle">
                <thead><tr>
                    <th>Día</th><th>Abierto</th><th>Abre</th><th>Última reserva</th><th>Cierra</th>
                    <th>Slot (min)</th><th>Turno (min)</th><th>Cupo/slot</th><th>Auto ≤</th>
                </tr></thead>
                <tbody>
                    @foreach ($dow as $d => $label)
                        @php($h = $hours[$d] ?? null)
                        <tr>
                            <td class="fw-medium">{{ $label }}</td>
                            <td><input type="checkbox" class="form-check-input" name="days[{{ $d }}][is_open]" value="1" {{ ($h->is_open ?? false) ? 'checked' : '' }}></td>
                            <td><input type="time" class="form-control form-control-sm" name="days[{{ $d }}][open_time]" value="{{ $h?->open_time ? substr($h->open_time,0,5) : '13:00' }}"></td>
                            <td><input type="time" class="form-control form-control-sm" name="days[{{ $d }}][last_seating]" value="{{ $h?->last_seating ? substr($h->last_seating,0,5) : '20:00' }}"></td>
                            <td><input type="time" class="form-control form-control-sm" name="days[{{ $d }}][close_time]" value="{{ $h?->close_time ? substr($h->close_time,0,5) : '22:00' }}"></td>
                            <td><input type="number" class="form-control form-control-sm" name="days[{{ $d }}][slot_minutes]" value="{{ $h->slot_minutes ?? 30 }}" min="5" max="240"></td>
                            <td><input type="number" class="form-control form-control-sm" name="days[{{ $d }}][turn_minutes]" value="{{ $h->turn_minutes ?? 120 }}" min="15" max="480"></td>
                            <td><input type="number" class="form-control form-control-sm" name="days[{{ $d }}][max_covers_per_slot]" value="{{ $h->max_covers_per_slot ?? 40 }}" min="1" max="1000"></td>
                            <td><input type="number" class="form-control form-control-sm" name="days[{{ $d }}][auto_confirm_max]" value="{{ $h->auto_confirm_max ?? 8 }}" min="1" max="1000"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button class="btn btn-primary btn-sm mt-2">Guardar horario y cupos</button>
    </form>
</div></div>

<div class="hp-card"><div class="hp-card-body">
    <h2 class="h6 fw-semibold mb-3">Ajustes generales</h2>
    <form method="POST" action="{{ route('settings.general') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label small fw-medium">Nombre del restaurante</label>
                <input name="restaurant_name" class="form-control" value="{{ $general['restaurant_name'] }}" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-medium">Correo de avisos a staff</label>
                <input name="staff_notify_email" type="email" class="form-control" value="{{ $general['staff_notify_email'] }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-medium">Idioma por defecto</label>
                <select name="default_locale" class="form-select">
                    <option value="es" {{ $general['default_locale']==='es'?'selected':'' }}>Español</option>
                    <option value="en" {{ $general['default_locale']==='en'?'selected':'' }}>Inglés</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" name="bot_enabled" value="1" id="bot_enabled" {{ $general['bot_enabled'] ? 'checked' : '' }}>
                    <label class="form-check-label small" for="bot_enabled">Bot activo</label>
                </div>
            </div>
            <div class="col-12"><hr class="my-1"><span class="text-secondary small">Recordatorio del mismo día</span></div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-medium">Modo</label>
                <select name="reminder_mode" class="form-select">
                    <option value="fixed_time" {{ $general['reminder_mode']==='fixed_time'?'selected':'' }}>Hora fija</option>
                    <option value="hours_before" {{ $general['reminder_mode']==='hours_before'?'selected':'' }}>Horas antes</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-medium">Hora fija</label>
                <input type="time" name="reminder_fixed_time" class="form-control" value="{{ $general['reminder_fixed_time'] }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-medium">Horas antes</label>
                <input type="number" name="reminder_hours_before" class="form-control" value="{{ $general['reminder_hours_before'] }}" min="1" max="24">
            </div>
        </div>
        <button class="btn btn-primary btn-sm mt-3">Guardar ajustes</button>
    </form>
</div></div>
@endsection
