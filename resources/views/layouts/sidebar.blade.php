<div class="sidebar-nav-container">
    <ul class="nav flex-column gap-1">
        {{-- OPERACIÓN Y ATENCIÓN: Visible para Cajeros, Meseros y Administradores --}}
        <div class="sidebar-heading text-uppercase fw-bold text-muted px-3 mt-2 mb-2" style="font-size: 0.72rem; letter-spacing: 0.06em;">
            OPERACIÓN Y ATENCIÓN
        </div>

        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('pos.*') ? 'active text-white' : 'text-secondary' }}" 
               href="{{ route('pos.index') }}"
               style="{{ request()->routeIs('pos.*') ? 'background-color: #10B981;' : '' }}">
                <i class="bi bi-calculator me-2 {{ request()->routeIs('pos.*') ? 'text-white' : 'text-success' }}"></i> POS Mostrador
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('restaurant.tables.*') ? 'active text-white' : 'text-secondary' }}" 
               href="{{ route('restaurant.tables.index') }}"
               style="{{ request()->routeIs('restaurant.tables.*') ? 'background-color: #10B981;' : '' }}">
                <i class="bi bi-grid-3x3-gap-fill me-2 {{ request()->routeIs('restaurant.tables.*') ? 'text-white' : 'text-success' }}"></i> Salón / Mesas
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('restaurant.orders.*') ? 'active text-white' : 'text-secondary' }}" 
               href="{{ route('restaurant.orders.index') }}"
               style="{{ request()->routeIs('restaurant.orders.*') ? 'background-color: #10B981;' : '' }}">
                <i class="bi bi-receipt-cutoff me-2 {{ request()->routeIs('restaurant.orders.*') ? 'text-white' : 'text-success' }}"></i> Comandas
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('restaurant.dispatch.*') ? 'active text-white' : 'text-secondary' }}" 
               href="{{ route('restaurant.dispatch.index') }}"
               style="{{ request()->routeIs('restaurant.dispatch.*') ? 'background-color: #10B981;' : '' }}">
                <i class="bi bi-bicycle me-2 {{ request()->routeIs('restaurant.dispatch.*') ? 'text-white' : 'text-success' }}"></i> Despacho / Domicilios
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('cash-register.*') || request()->routeIs('reports.cash-register') ? 'active text-white' : 'text-secondary' }}" 
               href="{{ route('cash-register.index') }}"
               style="{{ request()->routeIs('cash-register.*') || request()->routeIs('reports.cash-register') ? 'background-color: #10B981;' : '' }}">
                <i class="bi bi-cash-stack me-2 {{ request()->routeIs('cash-register.*') ? 'text-white' : 'text-success' }}"></i> Cuadre de Caja
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('credit.*') ? 'active text-white' : 'text-secondary' }}" 
               href="{{ route('credit.index') }}"
               style="{{ request()->routeIs('credit.*') ? 'background-color: #10B981;' : '' }}">
                <i class="bi bi-wallet2 me-2 {{ request()->routeIs('credit.*') ? 'text-white' : 'text-danger' }}"></i> Cartera / Fiados
            </a>
        </li>

        {{-- Clientes (Directorio Operativo) --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('customers.*') ? 'active text-white' : 'text-secondary' }}" 
               href="{{ route('customers.index') }}"
               style="{{ request()->routeIs('customers.*') ? 'background-color: #10B981;' : '' }}">
                <i class="bi bi-people-fill me-2 {{ request()->routeIs('customers.*') ? 'text-white' : 'text-success' }}"></i> Clientes
            </a>
        </li>

        @if (auth()->check() && auth()->user()->isAdmin())
            <hr class="my-2 border-secondary opacity-25">
            <div class="sidebar-heading text-uppercase fw-bold text-muted px-3 mt-2 mb-2" style="font-size: 0.72rem; letter-spacing: 0.06em;">
                GESTIÓN Y ADMINISTRACIÓN
            </div>

            {{-- Dashboard Financiero --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('dashboard') ? 'active text-white' : 'text-secondary' }}" 
                   href="{{ route('dashboard') }}"
                   style="{{ request()->routeIs('dashboard') ? 'background-color: #10B981;' : '' }}">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>

            {{-- Reportes Gerenciales --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('reports.*') ? 'active text-white' : 'text-secondary' }}" 
                   href="{{ route('reports.index') }}"
                   style="{{ request()->routeIs('reports.*') ? 'background-color: #10B981;' : '' }}">
                    <i class="bi bi-graph-up-arrow me-2 {{ request()->routeIs('reports.*') ? 'text-white' : 'text-success' }}"></i> Reportes
                </a>
            </li>

            {{-- Inventario e Insumos --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('inventory.*') ? 'active text-white' : 'text-secondary' }}" 
                   href="{{ route('inventory.index') }}"
                   style="{{ request()->routeIs('inventory.*') ? 'background-color: #10B981;' : '' }}">
                    <i class="bi bi-boxes me-2"></i> Inventario Físico
                </a>
            </li>

            {{-- Catálogo de Productos --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('products.*') ? 'active text-white' : 'text-secondary' }}" 
                   href="{{ route('products.index') }}"
                   style="{{ request()->routeIs('products.*') ? 'background-color: #10B981;' : '' }}">
                    <i class="bi bi-collection-fill me-2"></i> Catálogo Productos
                </a>
            </li>

            {{-- Fórmulas y Recetas --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('recipes.*') ? 'active text-white' : 'text-secondary' }}" 
                   href="{{ route('recipes.index') }}"
                   style="{{ request()->routeIs('recipes.*') ? 'background-color: #10B981;' : '' }}">
                    <i class="bi bi-book-half me-2"></i> Fórmulas y Recetas
                </a>
            </li>

            {{-- Adiciones y Extras --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('additions.*') ? 'active text-white' : 'text-secondary' }}" 
                   href="{{ route('additions.index') }}"
                   style="{{ request()->routeIs('additions.*') ? 'background-color: #10B981;' : '' }}">
                    <i class="bi bi-plus-square-fill me-2"></i> Adiciones y Extras
                </a>
            </li>

            {{-- Proveedores --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('suppliers.*') ? 'active text-white' : 'text-secondary' }}" 
                   href="{{ route('suppliers.index') }}"
                   style="{{ request()->routeIs('suppliers.*') ? 'background-color: #10B981;' : '' }}">
                    <i class="bi bi-truck me-2"></i> Proveedores
                </a>
            </li>

            {{-- Gastos y Facturas de Compra --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('expenses.*') ? 'active text-white' : 'text-secondary' }}" 
                   href="{{ route('expenses.index') }}"
                   style="{{ request()->routeIs('expenses.*') ? 'background-color: #10B981;' : '' }}">
                    <i class="bi bi-receipt-cutoff me-2"></i> Gastos y Facturas
                </a>
            </li>

            {{-- Equipo y Colaboradores --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('users.*') ? 'active text-white' : 'text-secondary' }}" 
                   href="{{ route('users.index') }}"
                   style="{{ request()->routeIs('users.*') ? 'background-color: #10B981;' : '' }}">
                    <i class="bi bi-people me-2"></i> Equipo y Colaboradores
                </a>
            </li>

            {{-- Asistente de Consultas IA --}}
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('ai.*') ? 'active text-white' : 'text-secondary' }}" 
                   href="{{ route('ai.index') }}"
                   style="{{ request()->routeIs('ai.*') ? 'background-color: #10B981;' : '' }}">
                    <i class="bi bi-stars me-2 {{ request()->routeIs('ai.*') ? 'text-white' : 'text-warning' }}"></i> Asistente IA
                </a>
            </li>
        @endif
    </ul>
</div>
