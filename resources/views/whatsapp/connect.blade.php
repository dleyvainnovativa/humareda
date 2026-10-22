@extends('layouts.app')
@section('title', 'WhatsApp')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h5 fw-bold mb-1">Números de WhatsApp</h1>
        <p class="text-secondary small mb-0">Conecta el número del negocio por <strong>Coexistencia</strong>: sigue en la app de WhatsApp y el bot responde desde el mismo número.</p>
    </div>
    <div>
        <button class="btn btn-sm btn-primary" id="wa-connect" @disabled(! $appId || ! $configId)>
            <i class="fa-brands fa-whatsapp me-1"></i>Conectar número
        </button>
    </div>
</div>

@if (! $appId || ! $configId)
    <div class="hp-card mb-3"><div class="hp-card-body">
        <div class="d-flex align-items-start gap-2">
            <i class="fa-solid fa-triangle-exclamation text-warning mt-1"></i>
            <div class="small">
                <div class="fw-semibold mb-1">Falta configurar el Tech Provider</div>
                <p class="text-secondary mb-1">Para activar el botón, define en el <code>.env</code> del servidor:</p>
                <ul class="text-secondary mb-0 ps-3">
                    <li><code>META_APP_ID</code> — el ID de la app de Meta</li>
                    <li><code>META_APP_SECRET</code> — el secreto de la app</li>
                    <li><code>META_ES_CONFIG_ID</code> — el ID de la configuración de Embedded Signup (tipo Coexistencia)</li>
                </ul>
            </div>
        </div>
    </div></div>
@endif

<div class="hp-card">
    <div class="hp-card-body">
        @if ($numbers->isEmpty())
            <div class="text-center py-5">
                <i class="fa-brands fa-whatsapp fa-lg text-secondary mb-2"></i>
                <p class="text-secondary small mb-1">Aún no hay números conectados.</p>
                <p class="text-secondary small mb-0">Mientras tanto, el bot usa el número de prueba configurado en el servidor.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="hp-table">
                    <thead><tr><th>Etiqueta</th><th>Phone Number ID</th><th>WABA ID</th><th>Tipo</th><th>Activo</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($numbers as $n)
                            <tr>
                                <td class="fw-medium">{{ $n->label ?: '—' }}</td>
                                <td class="hp-mono text-secondary small">{{ $n->phone_number_id }}</td>
                                <td class="hp-mono text-secondary small">{{ $n->waba_id ?: '—' }}</td>
                                <td>
                                    @if ($n->is_coexistence)
                                        <span class="hp-pill is-confirmed">Coexistencia</span>
                                    @else
                                        <span class="hp-pill is-pending">Dedicado</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input wa-toggle" type="checkbox" data-id="{{ $n->id }}" @checked($n->is_active)>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-danger wa-del" data-id="{{ $n->id }}" data-name="{{ $n->label ?: $n->phone_number_id }}"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const APP_ID      = @json($appId);
    const CONFIG_ID   = @json($configId);
    const GRAPH_VER   = @json($graphVer);
    const completeUrl = @json(route('whatsapp.connect.complete'));
    const toggleBase  = @json(url('/whatsapp'));

    // Session info posted by Embedded Signup (waba_id + phone_number_id).
    let sessionInfo = {};

    window.addEventListener('message', function (event) {
        if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return;
        let data;
        try { data = JSON.parse(event.data); } catch (e) { return; }
        if (data.type === 'WA_EMBEDDED_SIGNUP' && data.event === 'FINISH' && data.data) {
            sessionInfo.waba_id = data.data.waba_id;
            sessionInfo.phone_number_id = data.data.phone_number_id;
        }
    });

    window.fbAsyncInit = function () {
        if (!APP_ID) return;
        FB.init({ appId: APP_ID, cookie: true, xfbml: false, version: GRAPH_VER });
    };

    function onboard(code) {
        if (!sessionInfo.phone_number_id) {
            HP.toast('No se recibió la información del número. Intenta de nuevo.', 'error', 7000);
            return;
        }
        HP.http.post(completeUrl, {
            code: code,
            waba_id: sessionInfo.waba_id,
            phone_number_id: sessionInfo.phone_number_id,
            label: null,
        }).then(function () {
            HP.toast('Número conectado.', 'success');
            location.reload();
        }).catch(function (err) {
            HP.toast(err?.data?.message || 'No se pudo completar la conexión.', 'error', 8000);
        });
    }

    const connectBtn = document.getElementById('wa-connect');
    if (connectBtn) {
        connectBtn.addEventListener('click', function () {
            if (!APP_ID || !CONFIG_ID) { HP.toast('Configura META_APP_ID y META_ES_CONFIG_ID primero.', 'error', 7000); return; }
            sessionInfo = {};
            FB.login(function (response) {
                const code = response?.authResponse?.code;
                if (!code) { HP.toast('Conexión cancelada.', 'error'); return; }
                onboard(code);
            }, {
                config_id: CONFIG_ID,
                response_type: 'code',
                override_default_response_type: true,
                extras: { setup: {}, featureType: 'whatsapp_business_app_onboarding', sessionInfoVersion: '3' },
            });
        });
    }

    document.querySelectorAll('.wa-toggle').forEach(function (el) {
        el.addEventListener('change', function () {
            HP.http.post(`${toggleBase}/${el.dataset.id}/toggle`, {})
                .catch(function () { HP.toast('No se pudo actualizar.', 'error'); el.checked = !el.checked; });
        });
    });

    document.querySelectorAll('.wa-del').forEach(function (el) {
        el.addEventListener('click', function () {
            if (!confirm(`¿Quitar "${el.dataset.name}"? El bot dejará de usar este número.`)) return;
            HP.http.delete(`${toggleBase}/${el.dataset.id}`)
                .then(function () { HP.toast('Número quitado.', 'success'); location.reload(); })
                .catch(function () { HP.toast('No se pudo quitar.', 'error'); });
        });
    });
});
</script>
@endpush
