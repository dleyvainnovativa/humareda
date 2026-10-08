<header class="hp-topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-icon d-lg-none hp-sidebar-toggle" id="hp-sidebar-toggle" aria-label="Menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="fw-semibold">@yield('title', 'Panel')</span>
    </div>

    <div class="d-flex align-items-center gap-2">
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
