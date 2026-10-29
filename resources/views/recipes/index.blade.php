@extends('layouts.app')

@section('title', 'Fórmulas de Recetas por Porción')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1" style="color: #111827;">
            <i class="bi bi-journal-text me-2" style="color: #10B981;"></i>Fórmulas de Recetas por Porción
        </h3>
        <p class="text-muted small mb-0">
            Control de despiece e ingeniería de menú: define los gramos (g) y mililitros (ml) consumidos al vender cada plato fitness.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('products.index', ['tipo' => 'PLATO']) }}" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold">
            <i class="bi bi-egg-fried me-1"></i> Ver Platos en Carta
        </a>
        <a href="{{ route('additions.index') }}" class="btn btn-outline-primary rounded-pill px-3 fw-semibold">
            <i class="bi bi-plus-square-fill me-1"></i> Adiciones y Extras
        </a>
    </div>
</div>

{{-- Banner Informativo del Motor de Recetas --}}
<div class="card border-0 shadow-sm rounded-4 bg-white p-3 mb-4 border-start border-4 border-emerald" style="border-left-color: #10B981 !important;">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle p-2 bg-success bg-opacity-10 text-success fs-4">
            <i class="bi bi-shield-check"></i>
        </div>
        <div>
            <h6 class="fw-bold mb-1 text-dark">Política de Descuento Atómico y Cero Stock Negativo</h6>
            <p class="text-muted small mb-0">
                Al cobrar un plato en el punto de venta, el sistema descuenta automáticamente del inventario los gramos y mililitros exactos configurados en esta receta. Si algún insumo no cuenta con existencias suficientes, la venta no se procesa para evitar saldos negativos.
            </p>
        </div>
    </div>
</div>

<!-- Tabla de Platos y Estado de Recetas -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted" style="letter-spacing: 0.5px;">
                    <th class="ps-4">Plato Saludable</th>
                    <th>Categoría</th>
                    <th>Precio Carta</th>
                    <th>Estado de la Fórmula</th>
                    <th>Insumos Configurados</th>
                    <th class="text-end pe-4">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($dishes as $dish)
                    @php
                        $hasRecipe = $dish->receta && $dish->receta->items->isNotEmpty();
                        $itemsCount = $dish->receta ? $dish->receta->items->count() : 0;
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <span class="badge bg-light text-secondary border font-monospace small px-2 py-1">{{ $dish->sku }}</span>
                            <div class="fw-bold text-dark mt-1">{{ $dish->nombre }}</div>
                            @if($dish->descripcion)
                                <small class="text-muted d-block text-truncate" style="max-width: 280px;">{{ $dish->descripcion }}</small>
                            @endif
                        </td>
                        <td>
                            @if($dish->category)
                                <span class="badge bg-light text-dark border">{{ $dish->category->name }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="fw-bold text-dark">${{ number_format($dish->precio_venta, 0, ',', '.') }}</span>
                        </td>
                        <td>
                            @if ($hasRecipe)
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                    <i class="bi bi-check2-circle me-1"></i> Configurada ({{ $itemsCount }} insumos)
                                </span>
                            @else
                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 px-2 py-1 rounded-pill">
                                    <i class="bi bi-exclamation-circle me-1"></i> Sin ingredientes
                                </span>
                            @endif
                        </td>
                        <td>
                            @if ($hasRecipe)
                                <div class="d-flex flex-wrap gap-1" style="max-width: 320px;">
                                    @foreach ($dish->receta->items->take(4) as $item)
                                        <span class="badge bg-light text-secondary border small">
                                            {{ $item->ingredient->nombre ?? 'Insumo' }}: {{ (float)$item->cantidad_por_porcion }}{{ strtolower($item->unidad) == 'mililitro' ? 'ml' : 'g' }}
                                        </span>
                                    @endforeach
                                    @if($dish->receta->items->count() > 4)
                                        <span class="badge bg-light text-muted border small">+{{ $dish->receta->items->count() - 4 }} más</span>
                                    @endif
                                </div>
                            @else
                                <small class="text-danger"><i class="bi bi-info-circle me-1"></i>Requiere configuración para descontar inventario</small>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <a href="{{ route('recipes.edit', $dish) }}" class="btn btn-sm text-white rounded-pill px-3 fw-semibold shadow-sm" style="background-color: #10B981;">
                                <i class="bi bi-sliders me-1"></i> {{ $hasRecipe ? 'Editar Receta' : 'Configurar Fórmula' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No hay platos registrados en el catálogo de tipo PLATO.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($dishes->hasPages())
        <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-muted">Mostrando {{ $dishes->firstItem() }} - {{ $dishes->lastItem() }} de {{ $dishes->total() }} platos</span>
            {{ $dishes->links() }}
        </div>
    @endif
</div>
@endsection
