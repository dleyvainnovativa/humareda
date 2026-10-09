<!DOCTYPE html>
<html lang="es" data-bs-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión · Humareda Prime</title>
    <script>
        (function() {
            var m = document.cookie.match(/(?:^|; )hp-theme=([^;]+)/);
            var t = m ? m[1] : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>

    {{-- Favicons --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('img/favicon-16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/css/theme.css', 'resources/js/app.js', 'resources/js/firebase.js'])
</head>

<body>
    <div class="hp-auth">
        <div class="hp-auth-shell">
            <aside class="hp-auth-brand">
                <img class="hp-auth-flame" src="{{ asset('img/icon.png') }}" alt="" aria-hidden="true">
                <img class="hp-auth-wm" src="{{ asset('img/logo.png') }}" alt="Restaurante Humareda Prime">
                <p class="hp-auth-tag">Reservaciones por WhatsApp, autorizadas por tu equipo.</p>
                <ul>
                    <li><i class="fa-brands fa-whatsapp"></i> Solicitudes directas por WhatsApp</li>
                    <li><i class="fa-solid fa-circle-check"></i> Autoriza o rechaza en un toque</li>
                    <li><i class="fa-solid fa-bell"></i> Recordatorios automáticos el mismo día</li>
                </ul>
            </aside>

            <main class="hp-auth-form">
                <div class="hp-auth-inner">
                    <img class="hp-auth-mark" src="{{ asset('img/icon.png') }}" alt="Humareda Prime">
                    <h1>Bienvenido de vuelta</h1>
                    <p class="hp-auth-sub">Inicia sesión en el panel de reservaciones.</p>

                    <form id="hp-login-form">
                        <div class="hp-auth-field">
                            <label for="hp-email">Correo</label>
                            <input id="hp-email" type="email" name="email" required autocomplete="email">
                        </div>
                        <div class="hp-auth-field">
                            <label for="hp-password">Contraseña</label>
                            <input id="hp-password" type="password" name="password" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Entrar</button>

                        <div class="hp-auth-sep">o</div>

                        <button id="hp-google" type="button" class="btn btn-outline-secondary w-100">
                            <i class="fa-brands fa-google me-2"></i>Continuar con Google
                        </button>

                        <p id="hp-login-status" class="small mt-2 mb-0" role="alert"></p>
                    </form>

                    <div class="hp-auth-foot">Humareda Prime · Panel de reservaciones</div>
                </div>
            </main>
        </div>
    </div>

    <script>
        window.__FIREBASE_CONFIG__ = @json(config('services.firebase.web'));
        window.__AUTH_URL__ = @json(route('auth.firebase'));
    </script>
</body>

</html>