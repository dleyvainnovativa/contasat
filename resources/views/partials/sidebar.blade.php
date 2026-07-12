{{-- Sidebar navigation. Always visible on desktop; slides in over the content on
     mobile, dismissed via the close button, the backdrop, Escape, or navigating.
     The active client/period switcher lives here — the topbar shows what's
     active, the sidebar is where you change it. --}}

{{-- Backdrop: only rendered/visible on mobile via CSS. Tap to dismiss. --}}
<div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
        <span class="logo-mark"><i class="fa-solid fa-file-invoice-dollar"></i></span>
        <span>ContaSAT</span>

        {{-- Close button: mobile only (hidden on desktop via CSS). --}}
        <button class="sidebar__close d-lg-none" data-sidebar-close aria-label="Cerrar menú">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    {{-- Active context switcher. Visible on all sizes: the topbar reports the
         active client/period, this is where you go to change it. --}}
    <div class="sidebar__context">
        @if($workContext->hasClient())
            <a href="{{ route('clients.show', $workContext->client()) }}" class="context-switch">
                <span class="context-switch__label">Cliente activo</span>
                <span class="context-switch__value text-truncate">{{ $workContext->client()->display_name }}</span>
                @if($workContext->hasPeriod())
                    <span class="context-switch__period data">{{ $workContext->period()->label }}</span>
                @else
                    <span class="context-switch__period is-empty">Sin periodo</span>
                @endif
            </a>
        @else
            <a href="{{ route('clients.index') }}" class="context-switch is-empty">
                <span class="context-switch__label">Sin cliente activo</span>
                <span class="context-switch__value">Seleccionar cliente</span>
            </a>
        @endif
    </div>

    <nav class="sidebar__nav">
        <a href="{{ route('dashboard') }}"
           class="nav-link-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fa-solid fa-gauge-high"></i> Panel
        </a>
        <a href="{{ route('clients.index') }}"
           class="nav-link-item {{ request()->routeIs('clients.*') ? 'active' : '' }}">
            <i class="fa-solid fa-users"></i> Clientes
        </a>

        <div class="nav-section">Proceso</div>
         <a href="{{ route('sat.index') }}"
           class="nav-link-item {{ request()->routeIs('sat.*') ? 'active' : '' }}">
            <i class="fa-solid fa-cloud-arrow-down"></i> Descarga SAT
        </a>
        <a href="{{ route('invoices.index') }}"
           class="nav-link-item {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
            <i class="fa-solid fa-file-lines"></i> Facturas
        </a>
        <a href="{{ route('statements.index') }}"
           class="nav-link-item {{ request()->routeIs('statements.*') ? 'active' : '' }}">
            <i class="fa-solid fa-building-columns"></i> Estados de cuenta
        </a>
        <a href="{{ route('reconciliation.index') }}"
           class="nav-link-item {{ request()->routeIs('reconciliation.*') ? 'active' : '' }}">
            <i class="fa-solid fa-code-compare"></i> Conciliación
        </a>
        <a href="{{ route('reports.index') }}"
           class="nav-link-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
            <i class="fa-solid fa-chart-column"></i> Reportes
        </a>
        <a href="{{ route('contabilidad.index') }}"
           class="nav-link-item {{ request()->routeIs('contabilidad.*') ? 'active' : '' }}">
            <i class="fa-solid fa-file-code"></i> Contabilidad electrónica
        </a>
    </nav>
</aside>