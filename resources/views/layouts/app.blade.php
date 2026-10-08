<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · Humareda Prime</title>

    {{-- Apply saved theme before paint to avoid a flash --}}
    <script>
        (function () {
            var m = document.cookie.match(/(?:^|; )hp-theme=([^;]+)/);
            var t = m ? m[1] : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    {{-- Bootstrap 5 + theme.css + app.js via Vite --}}
    @vite(['resources/css/theme.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <div class="hp-app">
        @include('layouts.partials.sidebar')

        <div class="hp-main">
            @include('layouts.partials.topbar')
            <main class="hp-content">
                @yield('content')
            </main>
        </div>
    </div>

    <div class="hp-toast-wrap"></div>
    @stack('scripts')
</body>
</html>
