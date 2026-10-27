@extends('layouts.app')

@section('title', 'Historial y Trazabilidad de Movimientos | SaborCaliFit')

@section('content')
<div class="container-fluid px-2 px-md-3 py-3">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">
                <i class="bi bi-clock-history text-success me-2"></i>Historial de Movimientos de Inventario
            </h1>
            <p class="text-muted small mb-0">Trazabilidad inmutable de entradas, consumos por venta, mermas operativas y ajustes físicos.</p>
        </div>
        <div>
            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Volver a Inventario
            </a>
        </div>
    </div>

    {{-- Filtros de Búsqueda --}}
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('inventory.movements') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold text-muted mb-1">Producto / Insumo</label>
                    <select name="product_id" class="form-select form-select-sm">
                        <option value="">-- Todos los productos --</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" {{ (string)$productId === (string)$p->id ? 'selected' : '' }}>
                                [{{ $p->sku }}] {{ $p->nombre }} ({{ $p->unidad_base }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Tipo de Movimiento</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">-- Todos los tipos --</option>
                        <option value="ENTRADA_COMPRA" {{ $type === 'ENTRADA_COMPRA' ? 'selected' : '' }}>Entrada por Compra</option>
                        <option value="SALIDA_VENTA" {{ $type === 'SALIDA_VENTA' ? 'selected' : '' }}>Salida por Venta / Receta</option>
                        <option value="MERMA" {{ $type === 'MERMA' ? 'selected' : '' }}>Merma / Pérdida</option>
                        <option value="AJUSTE" {{ $type === 'AJUSTE' ? 'selected' : '' }}>Ajuste Conteo Físico</option>
                        <option value="DEVOLUCION_ANULACION" {{ $type === 'DEVOLUCION_ANULACION' ? 'selected' : '' }}>Devolución por Anulación</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Desde</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Hasta</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                </div>
                <div class="col-12 col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-califit btn-sm w-100 fw-bold" title="Filtrar">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('inventory.movements') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de Movimientos --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3" style="width: 140px;">Fecha y Hora</th>
                        <th>Producto</th>
                        <th style="width: 160px;">Tipo</th>
                        <th class="text-end" style="width: 130px;">Cantidad</th>
                        <th class="text-end" style="width: 120px;">Stock Anterior</th>
                        <th class="text-end" style="width: 120px;">Nuevo Stock</th>
                        <th>Motivo / Referencia</th>
                        <th style="width: 140px;">Usuario</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse($movements as $m)
                        @php
                            $badgeClass = match($m->type) {
                                'ENTRADA_COMPRA' => 'bg-success text-white',
                                'SALIDA_VENTA' => 'bg-danger text-white',
                                'MERMA' => 'bg-warning text-dark',
                                'AJUSTE' => 'bg-primary text-white',
                                'DEVOLUCION_ANULACION' => 'bg-secondary text-white',
                                default => 'bg-dark text-white',
                            };

                            $typeLabel = match($m->type) {
                                'ENTRADA_COMPRA' => 'Entrada Compra',
                                'SALIDA_VENTA' => 'Salida Venta',
                                'MERMA' => 'Merma / Pérdida',
                                'AJUSTE' => 'Ajuste Conteo',
                                'DEVOLUCION_ANULACION' => 'Dev. Anulación',
                                default => $m->type,
                            };

                            $isPositive = (float) $m->quantity > 0;
                            $qtyFormatted = number_format(abs((float) $m->quantity), 2, ',', '.');
                            $sign = $isPositive ? '+' : '-';
                            $qtyColor = $isPositive ? 'text-success' : 'text-danger';
                        @endphp
                        <tr>
                            <td class="ps-3 text-muted">
                                {{ $m->created_at->timezone('America/Bogota')->format('d/m/Y H:i') }}
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $m->product?->nombre ?? 'Producto Eliminado' }}</div>
                                <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">SKU: {{ $m->product?->sku ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $badgeClass }} px-2 py-1 rounded-pill fw-semibold">
                                    {{ $typeLabel }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold {{ $qtyColor }}">
                                {{ $sign }}{{ $qtyFormatted }} <span class="small text-muted">{{ $m->product?->unidad_base ?? '' }}</span>
                            </td>
                            <td class="text-end font-monospace text-muted">
                                {{ number_format((float) $m->previous_stock, 2, ',', '.') }}
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                {{ number_format((float) $m->new_stock, 2, ',', '.') }}
                            </td>
                            <td>
                                <span class="text-dark">{{ $m->reason ?? 'Sin motivo especificado' }}</span>
                                @if($m->reference_id)
                                    <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.7rem;">Ref: #{{ $m->reference_id }}</span>
                                @endif
                            </td>
                            <td class="text-muted">
                                <i class="bi bi-person me-1"></i>{{ $m->user?->name ?? 'Sistema' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-clock-history fs-2 d-block mb-2 text-secondary"></i>
                                No se encontraron movimientos de inventario con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
