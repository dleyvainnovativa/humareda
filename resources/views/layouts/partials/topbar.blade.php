<header class="hp-topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-icon d-lg-none hp-sidebar-toggle" id="hp-sidebar-toggle" aria-label="Menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="fw-semibold">@yield('title', 'Panel')</span>
    </div>

    <div class="d-flex align-items-center gap-2">
        {{-- Notification bell (additive) — polls /notifications/summary ~20s --}}
        <div class="dropdown" id="hp-notif">
            <button class="btn btn-icon position-relative" type="button" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside" aria-label="Notificaciones" id="hp-notif-btn">
                <i class="fa-solid fa-bell"></i>
                <span class="badge rounded-pill bg-danger position-absolute translate-middle d-none"
                      id="hp-notif-badge" style="top:.35rem;left:calc(100% - .35rem);font-size:.6rem;">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 shadow" style="min-width:320px;max-width:360px;">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                    <span class="fw-semibold">Notificaciones</span>
                    <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="hp-notif-clear">
                        Marcar leídas
                    </button>
                </div>
                <div id="hp-notif-list" style="max-height:360px;overflow:auto;">
                    <div class="text-secondary small px-3 py-4 text-center">Sin novedades</div>
                </div>
            </div>
        </div>

        <button class="btn btn-icon" data-theme-toggle aria-label="Cambiar tema">
            <i data-theme-icon class="fa-solid fa-moon"></i>
        </button>

        <div class="dropdown">
            <button class="btn btn-icon" data-bs-toggle="dropdown" aria-label="Cuenta">
                <i class="fa-solid fa-circle-user fs-5"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="dropdown-item-text small text-secondary">
                    {{ auth()->user()?->name }}<br>
                    <span class="text-muted">{{ auth()->user()?->email }}</span>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Cerrar sesión
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>

@push('scripts')
<script>
    (function () {
        var t = document.getElementById('hp-sidebar-toggle');
        var s = document.getElementById('hp-sidebar');
        var b = document.getElementById('hp-sidebar-backdrop');
        if (t) t.addEventListener('click', function () { s.classList.add('is-open'); b.hidden = false; });
        if (b) b.addEventListener('click', function () { s.classList.remove('is-open'); b.hidden = true; });
    })();
</script>
@endpush

@push('scripts')
{{-- Notification bell: pure client-side polling of /notifications/summary.
     Per-browser "seen" watermarks in localStorage; soft WebAudio beep + toast
     on a new inbound message / pending reservation / conversation needing a human. --}}
<script>
(function () {
    var root = document.getElementById('hp-notif');
    if (!root || !window.HP) return;

    var URL      = @json(route('notifications.summary'));
    var POLL_MS  = 20000;
    var badgeEl  = document.getElementById('hp-notif-badge');
    var listEl   = document.getElementById('hp-notif-list');
    var clearBtn = document.getElementById('hp-notif-clear');
    var btnEl    = document.getElementById('hp-notif-btn');

    var ACK_KEY  = 'hp-notif-ack';
    var prevSig  = null;     // last poll signature (in-memory) — gates the beep
    var firstRun = true;
    var audioCtx = null;

    function readAck() {
        try { return JSON.parse(localStorage.getItem(ACK_KEY)) || {}; }
        catch (e) { return {}; }
    }
    function writeAck(a) {
        try { localStorage.setItem(ACK_KEY, JSON.stringify(a)); } catch (e) {}
    }
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = (s == null ? '' : String(s));
        return d.innerHTML;
    }

    function beep() {
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            [0, 0.16].forEach(function (offset, i) {
                var o = audioCtx.createOscillator();
                var g = audioCtx.createGain();
                o.type = 'sine';
                o.frequency.value = i === 0 ? 660 : 880;
                g.gain.setValueAtTime(0.0001, audioCtx.currentTime + offset);
                g.gain.exponentialRampToValueAtTime(0.06, audioCtx.currentTime + offset + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + offset + 0.14);
                o.connect(g); g.connect(audioCtx.destination);
                o.start(audioCtx.currentTime + offset);
                o.stop(audioCtx.currentTime + offset + 0.15);
            });
        } catch (e) {}
    }

    function signatureOf(s) {
        return [s.inbound.max_id, s.reservations.max_id, s.attention.token].join('|');
    }
    function hasNew(s, ack) {
        return s.inbound.max_id      > (ack.inbound || 0)
            || s.reservations.max_id > (ack.res || 0)
            || (s.attention.count > 0 && s.attention.token !== ack.attn);
    }
    function currentAck(s) {
        return { inbound: s.inbound.max_id, res: s.reservations.max_id, attn: s.attention.token };
    }

    function renderBadge(s, isNew) {
        var standing = (s.reservations.pending || 0) + (s.attention.count || 0);
        if (standing > 0) {
            badgeEl.textContent = standing > 99 ? '99+' : standing;
            badgeEl.classList.remove('d-none');
            badgeEl.classList.toggle('bg-danger', true);
        } else if (isNew) {
            badgeEl.textContent = '';
            badgeEl.style.minWidth = '.55rem';
            badgeEl.style.height = '.55rem';
            badgeEl.style.padding = '0';
            badgeEl.classList.remove('d-none');
        } else {
            badgeEl.classList.add('d-none');
        }
    }

    function renderList(recent) {
        if (!recent || !recent.length) {
            listEl.innerHTML = '<div class="text-secondary small px-3 py-4 text-center">Sin novedades</div>';
            return;
        }
        listEl.innerHTML = recent.map(function (it) {
            return '<a href="' + esc(it.url) + '" class="dropdown-item d-flex align-items-start gap-2 py-2 border-bottom">'
                 +   '<i class="fa-solid ' + esc(it.icon) + ' mt-1 text-secondary"></i>'
                 +   '<span class="flex-grow-1" style="white-space:normal;min-width:0;">'
                 +     '<span class="d-block fw-semibold text-truncate">' + esc(it.title) + '</span>'
                 +     '<span class="d-block small text-secondary text-truncate">' + esc(it.subtitle) + '</span>'
                 +   '</span>'
                 +   '<span class="small text-muted flex-shrink-0">' + esc(it.at_label) + '</span>'
                 + '</a>';
        }).join('');
    }

    function apply(data) {
        var s = data.signals;
        var ack = readAck();
        var isNew = hasNew(s, ack);
        var sig = signatureOf(s);

        renderBadge(s, isNew);
        renderList(data.recent);

        // Beep + toast only when the signature actually changes (not every poll),
        // and never on the very first poll of the page.
        if (!firstRun && sig !== prevSig && isNew) {
            beep();
            window.HP.toast('Nueva actividad en el panel', 'info', 4000);
        }
        prevSig = sig;
        firstRun = false;
    }

    function poll() {
        window.HP.http.get(URL).then(apply).catch(function () { /* silent */ });
    }

    // Opening the bell, or "Marcar leídas", acknowledges current state.
    function acknowledge() {
        window.HP.http.get(URL).then(function (data) {
            writeAck(currentAck(data.signals));
            renderBadge(data.signals, false);
            renderList(data.recent);
            prevSig = signatureOf(data.signals);
        }).catch(function () {});
    }

    if (btnEl)    btnEl.addEventListener('click', function () { setTimeout(acknowledge, 0); });
    if (clearBtn) clearBtn.addEventListener('click', acknowledge);

    poll();
    setInterval(poll, POLL_MS);
})();
</script>
@endpush
