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
        <div class="nav-section">Configuración Global</div>
        <a href="{{ route('clients.index') }}"
            class="nav-link-item {{ request()->routeIs('clients.*') ? 'active' : '' }}">
            <i class="fa-solid fa-users"></i> Clientes
        </a>
        <div class="nav-section">Base de Datos</div>
        <a href="{{ route('catalog.index') }}"
            class="nav-link-item {{ request()->routeIs('catalog.*') ? 'active' : '' }}">
            <i class="fa-solid fa-layer-group"></i> Catálogo de cuentas
        </a>

        <div class="nav-section">Proceso</div>
        <a href="{{ route('accounts.index') }}"
            class="nav-link-item {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
            <i class="fa-solid fa-sitemap"></i> Catálogo de cuentas
        </a>
        <a href="{{ route('sat.index') }}"
            class="d-none nav-link-item {{ request()->routeIs('sat.*') ? 'active' : '' }}">
            <i class="fa-solid fa-cloud-arrow-down"></i> Descarga SAT
        </a>
        <div class="nav-group {{ request()->routeIs('invoices.*') ? 'open' : '' }}" data-nav-group>
            <a href="{{ route('invoices.index') }}"
                class="nav-link-item {{ request()->routeIs('invoices.index') ? 'active' : '' }}">
                <i class="fa-solid fa-file-lines"></i> Facturas
                <button class="nav-group__toggle" data-nav-toggle aria-label="Desplegar">
                    <i class="fa-solid fa-chevron-down"></i>
                </button>
            </a>
            <div class="nav-group__items">
                <a href="{{ route('invoices.view', 'ingreso') }}"
                    class="nav-sublink {{ request()->routeIs('invoices.view') && request()->route('view') === 'ingreso' ? 'active' : '' }}">
                    Provisión de ingreso
                </a>
                <a href="{{ route('invoices.view', 'gasto') }}"
                    class="nav-sublink {{ request()->routeIs('invoices.view') && request()->route('view') === 'gasto' ? 'active' : '' }}">
                    Provisión de gastos
                </a>
                <a href="{{ route('invoices.view', 'nomina') }}"
                    class="nav-sublink {{ request()->routeIs('invoices.view') && request()->route('view') === 'nomina' ? 'active' : '' }}">
                    Provisión de nómina
                </a>
                <a href="{{ route('invoices.view', 'pago_emitido') }}"
                    class="nav-sublink {{ request()->routeIs('invoices.view') && request()->route('view') === 'pago_emitido' ? 'active' : '' }}">
                    Complementos pago emitidos
                </a>
                <a href="{{ route('invoices.view', 'pago_recibido') }}"
                    class="nav-sublink {{ request()->routeIs('invoices.view') && request()->route('view') === 'pago_recibido' ? 'active' : '' }}">
                    Complementos pago recibidos
                </a>
            </div>
        </div>
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