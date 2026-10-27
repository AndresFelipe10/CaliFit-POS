@extends('layouts.app')

@section('title', 'Adiciones y Extras Reutilizables')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1" style="color: #111827;">
            <i class="bi bi-plus-square-fill me-2" style="color: #10B981;"></i>Adiciones y Extras Reutilizables
        </h3>
        <p class="text-muted small mb-0">
            Gestión de porciones adicionales con precio de venta al público y consumo exacto de insumos en gramos (g/ml).
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('recipes.index') }}" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold">
            <i class="bi bi-journal-text me-1 text-success"></i> Ver Recetas de Platos
        </a>
        <a href="{{ route('additions.create') }}" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background-color: #10B981;">
            <i class="bi bi-plus-circle-fill me-1"></i> Nueva Adición
        </a>
    </div>
</div>

{{-- Banner Informativo --}}
<div class="card border-0 shadow-sm rounded-4 bg-white p-3 mb-4 border-start border-4 border-emerald" style="border-left-color: #10B981 !important;">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle p-2 bg-success bg-opacity-10 text-success fs-4">
            <i class="bi bi-patch-check"></i>
        </div>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Adiciones con Precio y Consumo de Stock Propio</h6>
            <p class="text-muted small mb-0">
                Cada adición cobra un precio extra al cliente y deduce de forma atómica sus insumos en <code>adicion_detalle</code>. Además, se asocia solo a los platos autorizados mediante <code>producto_adicion</code>.
            </p>
        </div>
    </div>
</div>

<!-- Tabla de Adiciones -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted" style="letter-spacing: 0.5px;">
                    <th class="ps-4">Adición / Extra</th>
                    <th>Precio Venta</th>
                    <th>Insumos que Consume</th>
                    <th>Platos Autorizados</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($additions as $addition)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark">{{ $addition->nombre }}</div>
                            <small class="text-muted">ID: #{{ $addition->id }}</small>
                        </td>
                        <td>
                            <span class="fw-bold text-success fs-6">${{ number_format($addition->precio, 0, ',', '.') }}</span>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                @forelse ($addition->items as $item)
                                    <span class="badge bg-light text-secondary border font-monospace small">
                                        {{ $item->ingredient->nombre ?? 'Insumo' }}: {{ (float)$item->cantidad }}{{ strtolower($item->ingredient->unidad_base ?? 'g') == 'mililitro' ? 'ml' : 'g' }}
                                    </span>
                                @empty
                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 small">Sin insumos configurados</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1" style="max-width: 280px;">
                                @forelse ($addition->products as $dish)
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">
                                        {{ $dish->nombre }}
                                    </span>
                                @empty
                                    <span class="text-muted small">Disponible para todos los platos</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            <form action="{{ route('additions.toggle-status', $addition) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm p-0 border-0" title="Click para alternar estado">
                                    @if ($addition->activo)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                            <i class="bi bi-check-circle-fill me-1"></i> Activa
                                        </span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                                            <i class="bi bi-x-circle-fill me-1"></i> Inactiva
                                        </span>
                                    @endif
                                </button>
                            </form>
                        </td>
                        <td class="text-end pe-4">
                            <a href="{{ route('additions.edit', $addition) }}" class="btn btn-sm btn-light border rounded-pill px-3" title="Editar adición">
                                <i class="bi bi-pencil-square text-secondary me-1"></i> Editar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-plus-square fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No hay adiciones registradas en el sistema.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($additions->hasPages())
        <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-muted">Mostrando {{ $additions->firstItem() }} - {{ $additions->lastItem() }} de {{ $additions->total() }} adiciones</span>
            {{ $additions->links() }}
        </div>
    @endif
</div>
@endsection
