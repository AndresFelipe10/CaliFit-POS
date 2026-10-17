<div class="sidebar-nav-container">
    {{-- BLOQUE 1: OPERACIÓN Y ATENCIÓN (Visible para Administrador y Empleado) --}}
    <div class="sidebar-section-label">OPERACIÓN Y ATENCIÓN</div>
    <ul class="nav flex-column gap-1">
        {{-- 1. Dashboard --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('dashboard') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('dashboard') }}">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>

        {{-- 2. Ventas (Solo para Comercio / Retail; en Restaurantes las ventas se canalizan por Salón/Mesas, Comandas, Domicilios y Mostrador) --}}
        @if (! app(\App\Services\Tenant\TenantManager::class)->get()?->isRestaurant())
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('sales.*') && !request()->routeIs('reports.cash-register') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('sales.index') }}">
                    <i class="bi bi-cart-check me-2"></i> Ventas
                </a>
            </li>
        @endif

        {{-- Módulo Restaurante: Salón y Comandas (Solo Restaurante) --}}
        @if (app(\App\Services\Tenant\TenantManager::class)->get()?->isRestaurant())
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('restaurant.tables.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('restaurant.tables.index') }}">
                    <i class="bi bi-grid-3x3-gap-fill me-2"></i> Salón y Mesas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('restaurant.orders.*') && !request()->routeIs('restaurant.orders.deliveries') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('restaurant.orders.index') }}">
                    <i class="bi bi-receipt me-2"></i> Comandas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('restaurant.orders.deliveries') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('restaurant.orders.deliveries') }}">
                    <i class="bi bi-bicycle me-2"></i> Domicilios y Despacho
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('sales.*') && !request()->routeIs('reports.cash-register') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('sales.index') }}">
                    <i class="bi bi-receipt me-2"></i> Ventas de Mostrador
                </a>
            </li>
        @endif

        {{-- 3. Cuadre de Caja (Para Administrador y Empleados) --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('reports.cash-register') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('reports.cash-register') }}">
                <i class="bi bi-cash-coin me-2"></i> Cuadre de Caja
            </a>
        </li>

        {{-- 4. Clientes (Para Administrador y Empleados) --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('customers.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('customers.index') }}">
                <i class="bi bi-people-fill me-2"></i> Clientes
            </a>
        </li>

        {{-- 5. Asistente IA (Para Administrador y Empleados) --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('ai.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('ai.index') }}">
                <i class="bi bi-stars text-primary me-2"></i> Asistente IA
            </a>
        </li>
    </ul>

    {{-- BLOQUE 2: GESTIÓN Y ADMINISTRACIÓN (Exclusivo para Administrador) --}}
    @if (auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isCurrentAdmin()))
        <div class="sidebar-section-label mt-2">GESTIÓN Y ADMINISTRACIÓN</div>
        <ul class="nav flex-column gap-1">
            {{-- 6. Inventario --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('inventory.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('inventory.index') }}">
                    <i class="bi bi-arrow-left-right me-2"></i> Inventario
                </a>
            </li>

            @if (app(\App\Services\Tenant\TenantManager::class)->get()?->isRestaurant())
                {{-- Catálogo para Restaurante: Menú / Productos y Categorías --}}
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ request()->routeIs('products.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('products.index') }}">
                        <i class="bi bi-book-half me-2"></i> Menú / Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ request()->routeIs('categories.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('categories.index') }}">
                        <i class="bi bi-tags me-2"></i> Categorías
                    </a>
                </li>
            @else
                {{-- Productos y Categorías (Comercio / Retail) --}}
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ request()->routeIs('products.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('products.index') }}">
                        <i class="bi bi-boxes me-2"></i> Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold {{ request()->routeIs('categories.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('categories.index') }}">
                        <i class="bi bi-tags me-2"></i> Categorías
                    </a>
                </li>
            @endif

            {{-- 7. Proveedores --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('suppliers.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('suppliers.index') }}">
                    <i class="bi bi-truck me-2"></i> Proveedores
                </a>
            </li>

            {{-- 8. Gastos --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('expenses.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('expenses.index') }}">
                    <i class="bi bi-receipt-cutoff me-2"></i> Gastos
                </a>
            </li>

            {{-- 9. Reportes --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('reports.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('reports.index') }}">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i> Reportes
                </a>
            </li>

            {{-- 10. Mi Negocio --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('businesses.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('businesses.edit') }}">
                    <i class="bi bi-buildings me-2"></i> Mi negocio
                </a>
            </li>

            {{-- 11. Equipo --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('users.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('users.index') }}">
                    <i class="bi bi-person-badge me-2"></i> Equipo
                </a>
            </li>
        </ul>
    @endif
</div>
