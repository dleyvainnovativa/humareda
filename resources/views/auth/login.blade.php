<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión · Humareda Prime</title>
    <script>
        (function () {
            var m = document.cookie.match(/(?:^|; )hp-theme=([^;]+)/);
            var t = m ? m[1] : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/css/theme.css', 'resources/js/app.js', 'resources/js/firebase.js'])
</head>
<body>
    <div class="hp-auth">
        <div class="hp-auth-card">
            <div class="text-center mb-4">
                <span class="hp-brand-mark d-inline-grid mb-3" style="width:44px;height:44px;font-size:1.2rem;">
                    <i class="fa-solid fa-fire"></i>
                </span>
                <h1 class="h5 fw-bold mb-1">Humareda Prime</h1>
                <p class="text-secondary small mb-0">Panel de reservaciones</p>
            </div>

            <div class="hp-card">
                <div class="hp-card-body">
                    <form id="hp-login-form">
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Correo</label>
                            <input type="email" name="email" class="form-control" required autocomplete="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Contraseña</label>
                            <input type="password" name="password" class="form-control" required autocomplete="current-password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Entrar</button>
                    </form>

                    <div class="d-flex align-items-center gap-2 my-3 text-secondary small">
                        <hr class="flex-grow-1 m-0"><span>o</span><hr class="flex-grow-1 m-0">
                    </div>

                    <button id="hp-google" class="btn btn-outline-secondary w-100">
                        <i class="fa-brands fa-google me-2"></i>Continuar con Google
                    </button>

                    <p id="hp-login-status" class="small mt-2 mb-0" role="alert"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.__FIREBASE_CONFIG__ = @json(config('services.firebase.web'));
        window.__AUTH_URL__ = @json(route('auth.firebase'));
    </script>
</body>
</html>
