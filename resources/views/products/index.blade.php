@extends('layouts.app')

@section('title', 'Catálogo Maestro de Productos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1" style="color: #111827;">
            <i class="bi bi-box-seam me-2" style="color: #10B981;"></i>Catálogo Maestro de Productos
        </h3>
        <p class="text-muted small mb-0">
            Administración unificada de platos saludables, suplementos deportivos, insumos y recetas de SaborCaliFit.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold">
            <i class="bi bi-journal-text me-1 text-success"></i> Gestor de Recetas
        </a>
        <a href="{{ route('products.create') }}" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background-color: #10B981;">
            <i class="bi bi-plus-circle-fill me-1"></i> Nuevo Producto
        </a>
    </div>
</div>

{{-- Tarjetas de Pestañas y Filtro Rápido por Tipo --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-lg">
        <a href="{{ route('products.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 transition-hover {{ !request('tipo') ? 'border-start border-4 border-emerald bg-light' : 'bg-white' }}" style="{{ !request('tipo') ? 'border-left-color: #10B981 !important;' : '' }}">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Todos</span>
                        <h4 class="fw-bold mb-0 text-dark">{{ $totalCount }}</h4>
                    </div>
                    <div class="rounded-circle p-2 bg-secondary bg-opacity-10 text-secondary">
                        <i class="bi bi-grid-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg">
        <a href="{{ route('products.index', ['tipo' => 'PLATO']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 transition-hover {{ request('tipo') === 'PLATO' ? 'border-start border-4 border-success bg-light' : 'bg-white' }}" style="{{ request('tipo') === 'PLATO' ? 'border-left-color: #10B981 !important;' : '' }}">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Platos Carta</span>
                        <h4 class="fw-bold mb-0 text-success">{{ $platosCount }}</h4>
                    </div>
                    <div class="rounded-circle p-2 bg-success bg-opacity-10 text-success">
                        <i class="bi bi-egg-fried fs-5"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg">
        <a href="{{ route('products.index', ['tipo' => 'SUPLEMENTO']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 transition-hover {{ request('tipo') === 'SUPLEMENTO' ? 'border-start border-4 bg-light' : 'bg-white' }}" style="{{ request('tipo') === 'SUPLEMENTO' ? 'border-color: #7c3aed !important;' : '' }}">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Suplementos</span>
                        <h4 class="fw-bold mb-0" style="color: #6d28d9;">{{ $suplementosCount }}</h4>
                    </div>
                    <div class="rounded-circle p-2" style="background-color: #ede9fe; color: #6d28d9;">
                        <i class="bi bi-capsule fs-5"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg">
        <a href="{{ route('products.index', ['tipo' => 'INSUMO']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 transition-hover {{ request('tipo') === 'INSUMO' ? 'border-start border-4 border-warning bg-light' : 'bg-white' }}">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Insumos (g/ml)</span>
                        <h4 class="fw-bold mb-0 text-warning">{{ $insumosCount }}</h4>
                    </div>
                    <div class="rounded-circle p-2 bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-archive-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3 col-lg">
        <a href="{{ route('products.index', ['tipo' => 'PREPARACION']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100 transition-hover {{ request('tipo') === 'PREPARACION' ? 'border-start border-4 border-info bg-light' : 'bg-white' }}">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Preparaciones</span>
                        <h4 class="fw-bold mb-0 text-info">{{ $prepCount }}</h4>
                    </div>
                    <div class="rounded-circle p-2 bg-info bg-opacity-10 text-info">
                        <i class="bi bi-cup-hot-fill fs-5"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Filtros de Búsqueda -->
<div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
    <form method="GET" action="{{ route('products.index') }}" class="row g-2 align-items-center">
        @if(request('tipo'))
            <input type="hidden" name="tipo" value="{{ request('tipo') }}">
        @endif

        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por SKU, nombre del producto o descripción..." 
                       value="{{ request('search') }}">
            </div>
        </div>

        <div class="col-md-3">
            <select name="category_id" class="form-select">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">Todos los estados</option>
                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Activos</option>
                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactivos</option>
            </select>
        </div>

        <div class="col-md-2 text-end d-flex gap-2">
            <button type="submit" class="btn text-white rounded-pill flex-grow-1 fw-semibold" style="background-color: #10B981;">
                Filtrar
            </button>
            @if (request()->hasAny(['search', 'category_id', 'status', 'tipo']))
                <a href="{{ route('products.index') }}" class="btn btn-light rounded-pill text-secondary" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Tabla de Productos -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted" style="letter-spacing: 0.5px;">
                    <th class="ps-4">SKU / Producto</th>
                    <th>Tipo</th>
                    <th>Unidad</th>
                    <th>Stock Físico</th>
                    <th>P. Compra</th>
                    <th>P. Venta</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $prod)
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-2">
                                <div>
                                    <span class="badge bg-light text-secondary border font-monospace small px-2 py-1">{{ $prod->sku }}</span>
                                    <div class="fw-bold text-dark mt-1">{{ $prod->nombre }}</div>
                                    @if($prod->category)
                                        <small class="text-muted"><i class="bi bi-tag me-1"></i>{{ $prod->category->name }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @switch($prod->tipo_producto)
                                @case('PLATO')
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1 rounded-pill fw-semibold">
                                        <i class="bi bi-egg-fried me-1"></i> Plato
                                    </span>
                                    @break
                                @case('SUPLEMENTO')
                                    <span class="badge rounded-pill px-2.5 py-1 fw-semibold" style="background-color: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe;">
                                        <i class="bi bi-capsule me-1"></i> Suplemento
                                    </span>
                                    @break
                                @case('INSUMO')
                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2.5 py-1 rounded-pill fw-semibold">
                                        <i class="bi bi-archive me-1"></i> Insumo Base
                                    </span>
                                    @break
                                @case('PREPARACION')
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2.5 py-1 rounded-pill fw-semibold">
                                        <i class="bi bi-cup-hot me-1"></i> Preparación
                                    </span>
                                    @break
                            @endswitch
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary fw-semibold">
                                {{ $prod->unidad_base }}
                            </span>
                        </td>
                        <td>
                            @if($prod->tipo_producto === 'PLATO')
                                <span class="text-muted small" title="El stock se descuenta por ingredientes en recetas">
                                    <i class="bi bi-diagram-3 me-1"></i> Por Receta
                                </span>
                            @else
                                <div class="fw-bold {{ (float)$prod->stock <= (float)$prod->stock_minimo ? 'text-danger' : 'text-dark' }}">
                                    {{ $prod->formatted_stock }} <span class="small text-muted">{{ strtolower($prod->unidad_base) }}</span>
                                </div>
                                <small class="text-muted">Mín: {{ (float)$prod->stock_minimo }}</small>
                            @endif
                        </td>
                        <td>
                            <span class="text-muted">${{ number_format($prod->precio_compra, 0, ',', '.') }}</span>
                        </td>
                        <td>
                            <span class="fw-bold text-dark">${{ number_format($prod->precio_venta, 0, ',', '.') }}</span>
                        </td>
                        <td>
                            <form action="{{ route('products.toggle-status', $prod) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm p-0 border-0" title="Click para alternar estado">
                                    @if ($prod->activo)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                            <i class="bi bi-check-circle-fill me-1"></i> Activo
                                        </span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                                            <i class="bi bi-x-circle-fill me-1"></i> Inactivo
                                        </span>
                                    @endif
                                </button>
                            </form>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group">
                                @if($prod->tipo_producto === 'PLATO')
                                    <a href="{{ route('recipes.edit', $prod) }}" class="btn btn-sm btn-outline-success rounded-pill px-3 me-1 fw-semibold" title="Configurar porciones e insumos de la receta">
                                        <i class="bi bi-journal-text me-1"></i> Receta
                                    </a>
                                @endif
                                <a href="{{ route('products.edit', $prod) }}" class="btn btn-sm btn-light border rounded-pill px-3" title="Editar producto">
                                    <i class="bi bi-pencil-square text-secondary"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron productos registrados con los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($products->hasPages())
        <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-muted">Mostrando {{ $products->firstItem() }} - {{ $products->lastItem() }} de {{ $products->total() }} registros</span>
            {{ $products->links() }}
        </div>
    @endif
</div>
@endsection
