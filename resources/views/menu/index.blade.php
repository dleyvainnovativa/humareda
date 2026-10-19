@extends('layouts.app')
@section('title', 'Menú')

@php($typeLabels = ['fixed' => 'Fijo', 'per_100_g' => 'Por peso', 'not_listed' => 'No listado'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h5 fw-bold mb-1">Menú</h1>
        <p class="text-secondary small mb-0">El bot cotiza platillos y precios <strong>solo de lo que está activo</strong> aquí. Los cambios aplican en el siguiente mensaje.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" id="menu-new-cat"><i class="fa-solid fa-folder-plus me-1"></i>Nueva categoría</button>
        <button class="btn btn-sm btn-primary" id="menu-new-item" @disabled($categories->isEmpty())><i class="fa-solid fa-plus me-1"></i>Nuevo platillo</button>
    </div>
</div>

@if ($categories->isEmpty())
    <div class="hp-card"><div class="hp-card-body text-center py-5">
        <i class="fa-solid fa-utensils fa-lg text-secondary mb-2"></i>
        <p class="text-secondary small mb-1">Aún no hay menú cargado.</p>
        <p class="text-secondary small mb-0">Crea una categoría, o impórtalo con <code>php artisan menu:import ruta/al/menu.json</code>.</p>
    </div></div>
@endif

@foreach ($categories as $cat)
    <div class="hp-card mb-3">
        <div class="hp-card-body">
            <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-semibold">{{ $cat->name }}</span>
                        @unless ($cat->is_active)<span class="hp-pill is-cancelled">Inactiva</span>@endunless
                        <span class="text-secondary small hp-mono">{{ $cat->items->count() }}</span>
                    </div>
                    @if ($cat->description)<div class="text-secondary small">{{ $cat->description }}</div>@endif
                </div>
                <div class="text-nowrap">
                    @php($catData = ['id' => $cat->id, 'name' => $cat->name, 'description' => $cat->description, 'sort_order' => $cat->sort_order, 'is_active' => $cat->is_active])
                    <div class="form-check form-switch d-inline-block me-2 align-middle">
                        <input class="form-check-input menu-cat-toggle" type="checkbox" data-id="{{ $cat->id }}" @checked($cat->is_active)>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary menu-cat-edit" data-cat='@json($catData)'><i class="fa-solid fa-pen"></i></button>
                    <button class="btn btn-sm btn-outline-danger menu-cat-del" data-id="{{ $cat->id }}" data-name="{{ $cat->name }}"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>

            @if ($cat->items->isNotEmpty())
            <div class="table-responsive">
                <table class="hp-table">
                    <thead><tr><th>Platillo</th><th>Porción</th><th>Precio</th><th>Tipo</th><th>Activo</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($cat->items as $item)
                            @php($itemData = ['id' => $item->id, 'menu_category_id' => $item->menu_category_id, 'name' => $item->name, 'description' => $item->description, 'portion_qty' => $item->portion_qty, 'portion_unit' => $item->portion_unit, 'price' => $item->price, 'price_type' => $item->price_type, 'is_active' => $item->is_active])
                            <tr>
                                <td class="fw-medium">{{ $item->name }}
                                    @if ($item->description)<div class="text-secondary small">{{ \Illuminate\Support\Str::limit($item->description, 90) }}</div>@endif
                                </td>
                                <td class="hp-mono text-secondary small">{{ $item->portionLabel() ?: '—' }}</td>
                                <td class="small">{{ $item->priceLabel() }}</td>
                                <td><span class="hp-pill {{ $item->price_type === 'fixed' ? 'is-confirmed' : 'is-pending' }}">{{ $typeLabels[$item->price_type] ?? $item->price_type }}</span></td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input menu-item-toggle" type="checkbox" data-id="{{ $item->id }}" @checked($item->is_active)>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-secondary menu-item-edit" data-item='@json($itemData)'><i class="fa-solid fa-pen"></i></button>
                                    <button class="btn btn-sm btn-outline-danger menu-item-del" data-id="{{ $item->id }}" data-name="{{ $item->name }}"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <p class="text-secondary small mb-0">Sin platillos en esta categoría.</p>
            @endif
        </div>
    </div>
@endforeach

{{-- Modal platillo --}}
<div class="modal fade" id="menu-item-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:var(--hp-radius-lg);">
        <form id="menu-item-form">
            <div class="modal-header"><h5 class="modal-title" id="menu-item-title">Nuevo platillo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="mi-id">
                <div class="row g-2">
                    <div class="col-12 mb-2"><label class="form-label small fw-medium">Categoría *</label>
                        <select id="mi-cat" name="menu_category_id" class="form-select" required>
                            @foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach
                        </select></div>
                    <div class="col-12 mb-2"><label class="form-label small fw-medium">Nombre *</label>
                        <input id="mi-name" name="name" class="form-control" required maxlength="160"></div>
                    <div class="col-12 mb-2"><label class="form-label small fw-medium">Descripción</label>
                        <textarea id="mi-desc" name="description" class="form-control" rows="2" maxlength="800"></textarea></div>
                    <div class="col-4 mb-2"><label class="form-label small fw-medium">Porción</label>
                        <input id="mi-pqty" name="portion_qty" type="number" step="0.01" min="0" class="form-control"></div>
                    <div class="col-2 mb-2"><label class="form-label small fw-medium">Unidad</label>
                        <input id="mi-punit" name="portion_unit" class="form-control" placeholder="g" maxlength="16"></div>
                    <div class="col-3 mb-2"><label class="form-label small fw-medium">Tipo *</label>
                        <select id="mi-ptype" name="price_type" class="form-select">
                            <option value="fixed">Fijo</option>
                            <option value="per_100_g">Por peso (/100 g)</option>
                            <option value="not_listed">No listado</option>
                        </select></div>
                    <div class="col-3 mb-2"><label class="form-label small fw-medium">Precio (MXN)</label>
                        <input id="mi-price" name="price" type="number" step="0.01" min="0" class="form-control"></div>
                </div>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" id="mi-active" name="is_active" value="1" checked>
                    <label class="form-check-label small" for="mi-active">Activo</label>
                </div>
                <p class="form-text"><i class="fa-solid fa-circle-info me-1"></i>En “Por peso” el precio es por 100 g; el bot aclara que el total depende del tamaño.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div></div>
</div>

{{-- Modal categoría --}}
<div class="modal fade" id="menu-cat-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:var(--hp-radius-lg);">
        <form id="menu-cat-form">
            <div class="modal-header"><h5 class="modal-title" id="menu-cat-title">Nueva categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="mc-id">
                <div class="mb-2"><label class="form-label small fw-medium">Nombre *</label>
                    <input id="mc-name" name="name" class="form-control" required maxlength="120"></div>
                <div class="mb-2"><label class="form-label small fw-medium">Descripción</label>
                    <textarea id="mc-desc" name="description" class="form-control" rows="2" maxlength="500"></textarea></div>
                <div class="mb-2" style="max-width:120px;"><label class="form-label small fw-medium">Orden</label>
                    <input id="mc-order" name="sort_order" type="number" min="0" class="form-control" value="0"></div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="mc-active" name="is_active" value="1" checked>
                    <label class="form-check-label small" for="mc-active">Activa</label>
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
// Corre después del módulo diferido de Vite (app.js) para que HP exista.
document.addEventListener('DOMContentLoaded', function () {
    const itemStore = @json(route('menu.items.store'));
    const itemBase  = @json(url('menu/items'));
    const catStore  = @json(route('menu.categories.store'));
    const catBase   = @json(url('menu/categories'));

    const itemModal = HP.modal('#menu-item-modal');
    const catModal  = HP.modal('#menu-cat-modal');
    const itemForm  = document.getElementById('menu-item-form');
    const catForm   = document.getElementById('menu-cat-form');
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v ?? ''; };

    // precio deshabilitado cuando el tipo es "no listado"
    const ptype = document.getElementById('mi-ptype');
    const priceSync = () => { document.getElementById('mi-price').disabled = (ptype.value === 'not_listed'); };
    ptype.addEventListener('change', priceSync);

    // ---- Platillo: nuevo / editar
    document.getElementById('menu-new-item')?.addEventListener('click', () => {
        itemForm.reset(); set('mi-id', ''); ptype.value = 'fixed'; priceSync();
        document.getElementById('mi-active').checked = true;
        document.getElementById('menu-item-title').textContent = 'Nuevo platillo';
        itemModal.show();
    });
    document.querySelectorAll('.menu-item-edit').forEach(b => b.addEventListener('click', () => {
        const d = JSON.parse(b.dataset.item);
        set('mi-id', d.id); set('mi-cat', d.menu_category_id); set('mi-name', d.name); set('mi-desc', d.description);
        set('mi-pqty', d.portion_qty); set('mi-punit', d.portion_unit); set('mi-price', d.price);
        ptype.value = d.price_type || 'fixed'; priceSync();
        document.getElementById('mi-active').checked = !!d.is_active;
        document.getElementById('menu-item-title').textContent = 'Editar platillo';
        itemModal.show();
    }));
    itemForm.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const id = document.getElementById('mi-id').value;
        const data = HP.serializeForm(itemForm);
        data.is_active = document.getElementById('mi-active').checked ? 1 : 0;
        const btn = itemForm.querySelector('button[type=submit]'); HP.setLoading(btn, true);
        try {
            if (id) await HP.http.put(`${itemBase}/${id}`, data); else await HP.http.post(itemStore, data);
            HP.toast('Platillo guardado.', 'success'); location.reload();
        } catch (e) { HP.setLoading(btn, false); HP.toast(e?.data?.message || 'No se pudo guardar.', 'error', 6000); }
    });

    // ---- Categoría: nueva / editar
    document.getElementById('menu-new-cat')?.addEventListener('click', () => {
        catForm.reset(); set('mc-id', ''); set('mc-order', '0');
        document.getElementById('mc-active').checked = true;
        document.getElementById('menu-cat-title').textContent = 'Nueva categoría';
        catModal.show();
    });
    document.querySelectorAll('.menu-cat-edit').forEach(b => b.addEventListener('click', () => {
        const d = JSON.parse(b.dataset.cat);
        set('mc-id', d.id); set('mc-name', d.name); set('mc-desc', d.description); set('mc-order', d.sort_order ?? 0);
        document.getElementById('mc-active').checked = !!d.is_active;
        document.getElementById('menu-cat-title').textContent = 'Editar categoría';
        catModal.show();
    }));
    catForm.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const id = document.getElementById('mc-id').value;
        const data = HP.serializeForm(catForm);
        data.is_active = document.getElementById('mc-active').checked ? 1 : 0;
        const btn = catForm.querySelector('button[type=submit]'); HP.setLoading(btn, true);
        try {
            if (id) await HP.http.put(`${catBase}/${id}`, data); else await HP.http.post(catStore, data);
            HP.toast('Categoría guardada.', 'success'); location.reload();
        } catch (e) { HP.setLoading(btn, false); HP.toast(e?.data?.message || 'No se pudo guardar.', 'error', 6000); }
    });

    // ---- Toggles
    const wireToggle = (sel, base) => document.querySelectorAll(sel).forEach(t => t.addEventListener('change', async () => {
        try { await HP.http.post(`${base}/${t.dataset.id}/toggle`, {}); HP.toast('Actualizado.', 'success'); }
        catch (e) { t.checked = !t.checked; HP.toast('Error.', 'error'); }
    }));
    wireToggle('.menu-item-toggle', itemBase);
    wireToggle('.menu-cat-toggle', catBase);

    // ---- Eliminar
    document.querySelectorAll('.menu-item-del').forEach(b => b.addEventListener('click', async () => {
        if (!confirm(`¿Eliminar el platillo "${b.dataset.name}"?`)) return;
        try { await HP.http.delete(`${itemBase}/${b.dataset.id}`); HP.toast('Eliminado.', 'success'); location.reload(); }
        catch (e) { HP.toast('Error.', 'error'); }
    }));
    document.querySelectorAll('.menu-cat-del').forEach(b => b.addEventListener('click', async () => {
        if (!confirm(`¿Eliminar la categoría "${b.dataset.name}" y todos sus platillos?`)) return;
        try { await HP.http.delete(`${catBase}/${b.dataset.id}`); HP.toast('Eliminada.', 'success'); location.reload(); }
        catch (e) { HP.toast('Error.', 'error'); }
    }));
});
</script>
@endpush
