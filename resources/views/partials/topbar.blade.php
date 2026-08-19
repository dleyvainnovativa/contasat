{{-- Topbar: mobile menu toggle, the ACTIVE client/period context, theme toggle,
     and logout. The context stays visible at every screen size — knowing which
     client's books you're posting to is the most important state on screen, so it
     shrinks and truncates rather than moving behind a menu. --}}
<header class="topbar">
    <button class="theme-toggle d-lg-none" data-sidebar-toggle
        aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="topbar__context">
        @include('partials.topbar_switcher')
    </div>

    <div class="topbar__actions">
        <button class="theme-toggle" data-theme-toggle aria-label="Cambiar tema">
            <i class="fa-solid fa-moon"></i>
        </button>
        <button class="theme-toggle"
            onclick="App.http.post('{{ route('auth.logout') }}').then(r => window.location.href = r.redirect)"
            aria-label="Cerrar sesión" title="Cerrar sesión">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </button>
    </div>
</header>