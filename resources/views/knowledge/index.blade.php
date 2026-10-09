@extends('layouts.app')
@section('title', 'Base de conocimiento')

@section('content')
@php($labels = ['menu'=>'Menú','hours'=>'Horario','location'=>'Ubicación','dietary'=>'Dietético','policy'=>'Políticas','general'=>'General'])

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="h6 fw-semibold mb-0">Base de conocimiento</h2>
        <p class="text-secondary small mb-0">El bot responde preguntas usando <strong>solo</strong> estas entradas activas. Si algo no está aquí, pasa con una persona.</p>
    </div>
    <button class="btn btn-primary btn-sm" id="hp-new"><i class="fa-solid fa-plus me-1"></i>Nueva entrada</button>
</div>

<div class="hp-card">
    <div class="hp-card-body">
        @if ($entries->isEmpty())
            <p class="text-secondary small text-center py-4 mb-0">Sin entradas. Agrega la primera.</p>
        @else
            <div class="table-responsive">
                <table class="hp-table">
                    <thead><tr><th>Categoría</th><th>Pregunta</th><th>Respuesta</th><th>Activa</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($entries as $e)
                            <tr>
                                <td><span class="hp-pill is-confirmed">{{ $labels[$e->category] ?? $e->category }}</span></td>
                                <td class="fw-medium">{{ $e->question_es }}</td>
                                <td class="text-secondary small">{{ \Illuminate\Support\Str::limit($e->answer_es, 90) }}</td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input hp-toggle" type="checkbox" role="switch"
                                               data-id="{{ $e->id }}" {{ $e->is_active ? 'checked' : '' }}>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-secondary hp-edit"
                                            data-entry='@json($e)'><i class="fa-solid fa-pen"></i></button>
                                    <button class="btn btn-sm btn-outline-danger hp-delete"
                                            data-id="{{ $e->id }}"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Editor modal --}}
<div class="modal fade" id="hp-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--hp-radius-lg);">
            <form id="hp-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="hp-modal-title">Nueva entrada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="f-id">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Categoría</label>
                        <select name="category" id="f-category" class="form-select" required>
                            @foreach ($categories as $c)
                                <option value="{{ $c }}">{{ $labels[$c] ?? $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-12 col-md-6 mb-2">
                            <label class="form-label small fw-medium">Pregunta (ES) *</label>
                            <input name="question_es" id="f-q-es" class="form-control" required maxlength="255">
                        </div>
                        <div class="col-12 col-md-6 mb-2">
                            <label class="form-label small fw-medium">Pregunta (EN)</label>
                            <input name="question_en" id="f-q-en" class="form-control" maxlength="255">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-medium">Respuesta (ES) *</label>
                        <textarea name="answer_es" id="f-a-es" class="form-control" rows="3" required maxlength="2000"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-medium">Respuesta (EN)</label>
                        <textarea name="answer_en" id="f-a-en" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-6">
                            <label class="form-label small fw-medium">Orden</label>
                            <input type="number" name="sort_order" id="f-order" class="form-control" value="0" min="0" max="999">
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="f-active" checked>
                                <label class="form-check-label small" for="f-active">Activa</label>
                            </div>
                        </div>
                    </div>
                    <p class="text-secondary small mt-2 mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Para dietético/alergias, evita garantías absolutas; si no estás seguro, deja que pase con una persona.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Run after the deferred Vite module (app.js) has executed and defined
// window.HP. A plain IIFE here runs during parse — before app.js — so HP
// would be undefined. DOMContentLoaded fires after deferred modules run.
document.addEventListener('DOMContentLoaded', function () {
    const storeUrl = @json(route('knowledge.store'));
    const base     = @json(url('knowledge'));
    const modalEl  = document.getElementById('hp-modal');
    const modal    = HP.modal(modalEl);
    const form     = document.getElementById('hp-form');
    const set = (id, v) => document.getElementById(id).value = v ?? '';

    function openNew() {
        form.reset();
        set('f-id', '');
        document.getElementById('f-active').checked = true;
        document.getElementById('hp-modal-title').textContent = 'Nueva entrada';
        modal.show();
    }
    function openEdit(e) {
        set('f-id', e.id); set('f-category', e.category);
        set('f-q-es', e.question_es); set('f-q-en', e.question_en);
        set('f-a-es', e.answer_es); set('f-a-en', e.answer_en);
        set('f-order', e.sort_order);
        document.getElementById('f-active').checked = !!e.is_active;
        document.getElementById('hp-modal-title').textContent = 'Editar entrada';
        modal.show();
    }

    document.getElementById('hp-new').addEventListener('click', openNew);
    document.querySelectorAll('.hp-edit').forEach(b =>
        b.addEventListener('click', () => openEdit(JSON.parse(b.dataset.entry))));

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const data = HP.serializeForm(form);
        data.is_active = document.getElementById('f-active').checked;
        const id = data.id;
        const url = id ? `${base}/${id}` : storeUrl;
        const btn = form.querySelector('button[type=submit]');
        HP.setLoading(btn, true);
        try {
            await (id ? HP.http.put(url, data) : HP.http.post(url, data));
            HP.toast('Guardado.', 'success'); location.reload();
        } catch (err) {
            HP.setLoading(btn, false);
            HP.toast(err?.data?.message || 'No se pudo guardar.', 'error', 6000);
        }
    });

    document.querySelectorAll('.hp-toggle').forEach(t =>
        t.addEventListener('change', async () => {
            try { await HP.http.post(`${base}/${t.dataset.id}/toggle`, {}); HP.toast('Actualizado.', 'success'); }
            catch (e) { t.checked = !t.checked; HP.toast('Error.', 'error'); }
        }));

    document.querySelectorAll('.hp-delete').forEach(b =>
        b.addEventListener('click', async () => {
            if (!confirm('¿Eliminar esta entrada?')) return;
            try { await HP.http.delete(`${base}/${b.dataset.id}`); HP.toast('Eliminada.', 'success'); location.reload(); }
            catch (e) { HP.toast('Error.', 'error'); }
        }));
});
</script>
@endpush