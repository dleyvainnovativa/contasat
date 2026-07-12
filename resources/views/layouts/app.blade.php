<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ContaSAT')</title>

     {{-- Anti-FOUC: set the theme BEFORE first paint. Must stay inline and
         blocking — a deferred module runs too late and the light theme flashes. --}}
    <script>
        (function () {
            var stored = localStorage.getItem('contasat-theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
        })();
    </script>

    {{-- Fonts: Inter (UI) + JetBrains Mono (data) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;550;600;650;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    {{-- Font Awesome --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    {{-- Bootstrap 5 + theme.css + app.js via Vite --}}
    @vite(['resources/css/theme.css', 'resources/js/app.js'])

    @session('toast')
        <meta name="toast" data-type="{{ session('toast')['type'] }}" content="{{ session('toast')['message'] }}">
    @endsession
</head>
<body>
<div class="app-shell">

    @include('partials.sidebar')

    <div class="main">
        @include('partials.topbar')

        <main class="content">
            @yield('content')
        </main>
    </div>
</div>

@include('partials.confirm-modal')

{{-- Bootstrap JS bundle (modals, dropdowns) --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
