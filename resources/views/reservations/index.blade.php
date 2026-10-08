@extends('layouts.app')
@section('title', 'Reservaciones')

@section('content')
@php($labels = ['pending'=>'En revisión','confirmed'=>'Confirmada','cancelled'=>'Cancelada','rejected'=>'Rechazada','completed'=>'Completada','no_show'=>'No llegó'])
@php($pill = ['pending'=>'is-pending','confirmed'=>'is-confirmed','cancelled'=>'is-cancelled','rejected'=>'is-cancelled','completed'=>'is-confirmed','no_show'=>'is-cancelled'])

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="btn-group btn-group-sm" role="group">
        <a href="{{ route('reservations.index', ['status'=>'pending']) }}" class="btn {{ $status==='pending'?'btn-primary':'btn-outline-secondary' }}">
            Por autorizar @if($pendingCount)<span class="badge bg-light text-dark ms-1">{{ $pendingCount }}</span>@endif
        </a>
        <a href="{{ route('reservations.index', ['status'=>'confirmed','date'=>$date]) }}" class="btn {{ $status==='confirmed'?'btn-primary':'btn-outline-secondary' }}">Confirmadas</a>
        <a href="{{ route('reservations.index', ['status'=>'all','date'=>$date]) }}" class="btn {{ $status==='all'?'btn-primary':'btn-outline-secondary' }}">Todas</a>
    </div>
    <div class="d-flex align-items-center gap-2">
        @if ($status !== 'pending')
            <form method="GET"><input type="hidden" name="status" value="{{ $status }}">
                <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()"></form>
        @endif
        <button class="btn btn-primary btn-sm" id="hp-new"><i class="fa-solid fa-plus me-1"></i>Nueva</button>
    </div>
</div>

<div class="hp-card"><div class="hp-card-body">
    @if ($reservations->isEmpty())
        <p class="text-secondary small text-center py-4 mb-0">
            {{ $status==='pending' ? 'Nada por autorizar. 👌' : 'Sin reservaciones.' }}
        </p>
    @elseif ($status === 'pending')
        {{-- Authorization queue --}}
        <div class="table-responsive"><table class="hp-table">
            <thead><tr><th>Fecha / Hora</th><th>Nombre</th><th>Personas</th><th>Referencia</th><th>Carga del día</th><th></th></tr></thead>
            <tbody>
                @foreach ($reservations as $r)
                    @php($l = $load[$r->id] ?? ['day'=>0,'around'=>0])
                    <tr>
                        <td class="hp-mono">{{ $r->reserved_date->format('d M') }} · {{ \Illuminate\Support\Carbon::parse($r->reserved_time)->format('H:i') }}</td>
                        <td class="fw-medium">{{ $r->name }}</td>
                        <td class="hp-mono">{{ $r->party_size }}</td>
                        <td class="text-secondary small">{{ $r->reference_contact ?? $r->contact?->wa_id }}</td>
                        <td class="small">
                            <span title="Confirmados ese día">{{ $l['day'] }} <span class="text-secondary">día</span></span> ·
                            <span title="Confirmados alrededor de esa hora">{{ $l['around'] }} <span class="text-secondary">±turno</span></span>
                        </td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-success hp-approve" data-id="{{ $r->id }}"><i class="fa-solid fa-check me-1"></i>Autorizar</button>
                            <button class="btn btn-sm btn-outline-danger hp-reject" data-id="{{ $r->id }}">Rechazar</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @else
        <div class="table-responsive"><table class="hp-table">
            <thead><tr><th>Hora</th><th>Nombre</th><th>Personas</th><th>Teléfono</th><th>Origen</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                @foreach ($reservations as $r)
                    <tr>
                        <td class="hp-mono">{{ \Illuminate\Support\Carbon::parse($r->reserved_time)->format('H:i') }}</td>
                        <td class="fw-medium">{{ $r->name }}</td>
                        <td class="hp-mono">{{ $r->party_size }}</td>
                        <td class="text-secondary hp-mono">{{ $r->contact?->wa_id }}</td>
                        <td class="text-secondary small">{{ $r->source }}</td>
                        <td><span class="hp-pill {{ $pill[$r->status] ?? '' }}">{{ $labels[$r->status] ?? $r->status }}</span></td>
                        <td class="text-end text-nowrap">
                            @if ($r->status === 'confirmed')
                                <button class="btn btn-sm btn-outline-secondary hp-edit"
                                    data-r='@json(['id'=>$r->id,'name'=>$r->name,'date'=>$r->reserved_date->toDateString(),'time'=>\Illuminate\Support\Carbon::parse($r->reserved_time)->format('H:i'),'party_size'=>$r->party_size])'>
                                    <i class="fa-solid fa-pen"></i></button>
                                <button class="btn btn-sm btn-outline-danger hp-cancel" data-id="{{ $r->id }}"><i class="fa-solid fa-xmark"></i></button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
</div></div>

<div class="modal fade" id="hp-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:var(--hp-radius-lg);">
        <form id="hp-form">
            <div class="modal-header"><h5 class="modal-title" id="hp-title">Nueva reservación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="f-id">
                <div class="row g-2">
                    <div class="col-12 col-md-6 mb-2"><label class="form-label small fw-medium">Nombre *</label>
                        <input id="f-name" name="name" class="form-control" required maxlength="120"></div>
                    <div class="col-12 col-md-6 mb-2"><label class="form-label small fw-medium">Teléfono (WhatsApp) *</label>
                        <input id="f-phone" name="phone" class="form-control" placeholder="5212291234567" maxlength="20"></div>
                    <div class="col-12 mb-2" id="f-ref-wrap"><label class="form-label small fw-medium">Correo/teléfono de referencia</label>
                        <input id="f-ref" name="reference_contact" class="form-control" maxlength="160"></div>
                    <div class="col-6 mb-2"><label class="form-label small fw-medium">Fecha *</label>
                        <input type="date" id="f-date" name="date" class="form-control" required></div>
                    <div class="col-3 mb-2"><label class="form-label small fw-medium">Hora *</label>
                        <input type="time" id="f-time" name="time" class="form-control" required></div>
                    <div class="col-3 mb-2"><label class="form-label small fw-medium">Personas *</label>
                        <input type="number" id="f-party" name="party_size" class="form-control" min="1" value="2" required></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const storeUrl = @json(route('reservations.store'));
    const base = @json(url('reservations'));
    const modal = HP.modal('#hp-modal');
    const form = document.getElementById('hp-form');
    const set = (id,v)=>document.getElementById(id).value = v ?? '';

    document.getElementById('hp-new').addEventListener('click', () => {
        form.reset(); set('f-id',''); document.getElementById('f-phone').disabled = false;
        document.getElementById('f-ref-wrap').style.display = '';
        document.getElementById('hp-title').textContent = 'Nueva reservación';
        set('f-date', @json($date)); modal.show();
    });
    document.querySelectorAll('.hp-edit').forEach(b => b.addEventListener('click', () => {
        const r = JSON.parse(b.dataset.r);
        set('f-id', r.id); set('f-name', r.name); set('f-date', r.date); set('f-time', r.time); set('f-party', r.party_size);
        document.getElementById('f-phone').value=''; document.getElementById('f-phone').disabled = true;
        document.getElementById('f-ref-wrap').style.display = 'none';
        document.getElementById('hp-title').textContent = 'Editar reservación';
        modal.show();
    }));

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const id = document.getElementById('f-id').value;
        const data = HP.serializeForm(form);
        const btn = form.querySelector('button[type=submit]'); HP.setLoading(btn, true);
        try {
            if (id) await HP.http.put(`${base}/${id}`, data); else await HP.http.post(storeUrl, data);
            HP.toast('Guardada.', 'success'); location.reload();
        } catch (err) { HP.setLoading(btn, false); HP.toast(err?.data?.message || 'No se pudo guardar.', 'error', 7000); }
    });

    const act = (sel, path, msg) => document.querySelectorAll(sel).forEach(b => b.addEventListener('click', async () => {
        if (path === 'reject' && !confirm('¿Rechazar esta solicitud? Se avisará al cliente.')) return;
        if (path === 'cancel' && !confirm('¿Cancelar esta reservación?')) return;
        HP.setLoading(b, true);
        try { await HP.http.post(`${base}/${b.dataset.id}/${path}`, {}); HP.toast(msg, 'success'); location.reload(); }
        catch (e) { HP.setLoading(b, false); HP.toast('Error.', 'error'); }
    }));
    act('.hp-approve', 'approve', 'Autorizada — se avisó al cliente.');
    act('.hp-reject',  'reject',  'Rechazada.');
    act('.hp-cancel',  'cancel',  'Cancelada.');
})();
</script>
@endpush
