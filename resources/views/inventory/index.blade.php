@extends('layouts.app')

@section('title', 'Control de Inventario e Insumos | SaborCaliFit')

@section('content')
<div class="container-fluid px-2 px-md-3 py-3">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">
                <i class="bi bi-boxes text-success me-2"></i>Inventario y Stock Físico
            </h1>
            <p class="text-muted small mb-0">Control de insumos en gramos/ml, suplementos por unidad, entradas y mermas.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-califit btn-sm px-3 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#entryModal">
                <i class="bi bi-box-arrow-in-down me-1"></i> Entrada por Compra
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#wasteModal">
                <i class="bi bi-trash3 me-1"></i> Registrar Merma
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#adjustmentModal">
                <i class="bi bi-sliders me-1"></i> Ajuste por Conteo
            </button>
            <a href="{{ route('inventory.movements') }}" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-semibold">
                <i class="bi bi-clock-history me-1"></i> Historial Movimientos
            </a>
        </div>
    </div>

    {{-- Tarjetas Resumen de Estado y Alertas --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 rounded-3 text-center border-start border-4 border-danger">
                <small class="text-muted fw-semibold d-block">Agotados (Stock Cero)</small>
                <h4 class="fw-bold mb-0 text-danger">{{ $outOfStockCount }}</h4>
                <small class="text-muted" style="font-size: 0.72rem;">Sin existencias para recetas</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 rounded-3 text-center border-start border-4 border-warning">
                <small class="text-muted fw-semibold d-block">Bajo Stock Mínimo</small>
                <h4 class="fw-bold mb-0 text-warning">{{ $lowStockCount }}</h4>
                <small class="text-muted" style="font-size: 0.72rem;">Requieren reposición urgente</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 rounded-3 text-center border-start border-4 border-success">
                <small class="text-muted fw-semibold d-block">Total Productos Activos</small>
                <h4 class="fw-bold mb-0 text-success">{{ $products->count() }}</h4>
                <small class="text-muted" style="font-size: 0.72rem;">Insumos, suplementos y platos</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 rounded-3 text-center border-start border-4 border-info">
                <small class="text-muted fw-semibold d-block">Valor Estimado en Stock</small>
                <h4 class="fw-bold mb-0 text-dark">${{ number_format($totalEstimatedValue, 0, ',', '.') }}</h4>
                <small class="text-muted" style="font-size: 0.72rem;">Costo promedio de compra</small>
            </div>
        </div>
    </div>

    {{-- Filtro de Categoría y Buscador --}}
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" id="inventorySearch" class="form-control border-start-0 ps-0" placeholder="Buscar por SKU o nombre..." autocomplete="off">
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="btn-group w-100" role="group">
                        <button type="button" class="btn btn-outline-success active inventory-filter-btn" data-type="all">Todos ({{ $products->count() }})</button>
                        <button type="button" class="btn btn-outline-success inventory-filter-btn" data-type="INSUMO">Insumos Cocina ({{ $insumos->count() }})</button>
                        <button type="button" class="btn btn-outline-success inventory-filter-btn" data-type="SUPLEMENTO">Suplementos ({{ $suplementos->count() }})</button>
                        <button type="button" class="btn btn-outline-success inventory-filter-btn" data-type="PLATO">Platos Fit ({{ $platos->count() }})</button>
                        <button type="button" class="btn btn-outline-success inventory-filter-btn" data-type="PREPARACION">Bases Prep ({{ $preparaciones->count() }})</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TABLA DE CATÁLOGO Y ALERTAS --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="inventoryTable">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3" style="width: 100px;">SKU</th>
                        <th>Producto / Insumo</th>
                        <th style="width: 130px;">Tipo</th>
                        <th class="text-center" style="width: 110px;">Unidad</th>
                        <th class="text-end" style="width: 130px;">Stock Actual</th>
                        <th class="text-end" style="width: 120px;">Mínimo</th>
                        <th class="text-center" style="width: 130px;">Estado</th>
                        <th class="text-end" style="width: 140px;">Sugerido Comprar</th>
                        <th class="text-end pe-3" style="width: 140px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                        <tr class="inventory-row" data-type="{{ $p->tipo_producto }}" data-name="{{ strtolower($p->nombre) }}" data-sku="{{ strtolower($p->sku) }}">
                            <td class="ps-3 fw-bold text-muted small">{{ $p->sku }}</td>
                            <td>
                                <div class="fw-bold text-dark">{{ $p->nombre }}</div>
                                <small class="text-muted">{{ $p->category->name ?? 'General' }}</small>
                            </td>
                            <td>
                                @php
                                    $typeBadge = match($p->tipo_producto) {
                                        'INSUMO' => 'bg-secondary-subtle text-secondary',
                                        'SUPLEMENTO' => 'bg-primary-subtle text-primary',
                                        'PLATO' => 'bg-success-subtle text-success',
                                        'PREPARACION' => 'bg-info-subtle text-info',
                                        default => 'bg-light text-dark'
                                    };
                                @endphp
                                <span class="badge {{ $typeBadge }} rounded-pill px-2 py-1 small">{{ $p->tipo_producto }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-secondary border small">{{ $p->unidad_base }}</span>
                            </td>
                            <td class="text-end fw-black {{ $p->status_stock === 'agotado' ? 'text-danger' : ($p->status_stock === 'bajo' ? 'text-warning' : 'text-dark') }}">
                                {{ $p->formatted_stock }}
                            </td>
                            <td class="text-end text-muted small">
                                {{ (float)$p->stock_minimo > 0 ? (float)$p->stock_minimo : '-' }}
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $p->status_class }} rounded-pill px-2.5 py-1 fw-bold" style="font-size: 0.75rem;">
                                    {{ $p->status_label }}
                                </span>
                            </td>
                            <td class="text-end fw-bold text-secondary">
                                @if((float)$p->sugerido_reposicion > 0)
                                    <span class="text-danger fw-bold">+{{ (float)$p->sugerido_reposicion }}</span>
                                @else
                                    <span class="text-muted small">Al día</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex justify-content-end gap-1">
                                    <button type="button" class="btn btn-outline-success btn-sm p-1" title="Entrada de Compra" onclick="openEntryForProduct({{ $p->id }}, '{{ addslashes($p->nombre) }}', '{{ $p->unidad_base }}')">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm p-1" title="Registrar Merma" onclick="openWasteForProduct({{ $p->id }}, '{{ addslashes($p->nombre) }}', '{{ $p->unidad_base }}')">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-sm p-1" title="Ajuste Conteo Físico" onclick="openAdjustmentForProduct({{ $p->id }}, '{{ addslashes($p->nombre) }}', {{ (float)$p->stock }}, '{{ $p->unidad_base }}')">
                                        <i class="bi bi-sliders"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No hay productos registrados en el inventario.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- MODAL 1: ENTRADA POR COMPRA CON CONVERSIÓN EN VIVO --}}
<div class="modal fade" id="entryModal" tabindex="-1" aria-labelledby="entryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('inventory.entries.store') }}">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title fw-bold" id="entryModalLabel">
                        <i class="bi bi-box-arrow-in-down text-success me-1"></i> Registrar Entrada por Compra
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label for="entry_product_id" class="form-label small fw-semibold">Producto / Insumo:</label>
                        <select name="product_id" id="entry_product_id" class="form-select" required onchange="calculateEntryConversion()">
                            <option value="">-- Seleccionar Producto --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" data-base="{{ $p->unidad_base }}" data-cost="{{ $p->precio_compra }}">
                                    {{ $p->nombre }} (Base: {{ $p->unidad_base }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="purchase_quantity" class="form-label small fw-semibold">Cantidad Comprada:</label>
                            <input type="number" name="purchase_quantity" id="purchase_quantity" class="form-control" step="0.001" min="0.001" value="1" required oninput="calculateEntryConversion()">
                        </div>
                        <div class="col-6">
                            <label for="purchase_unit" class="form-label small fw-semibold">Unidad de Compra:</label>
                            <select name="purchase_unit" id="purchase_unit" class="form-select" required onchange="calculateEntryConversion()">
                                <option value="libra">Libras (1 lb = 500 g)</option>
                                <option value="kilo">Kilos (1 kg = 1000 g)</option>
                                <option value="litro">Litros (1 lt = 1000 ml)</option>
                                <option value="gramo">Gramos (g)</option>
                                <option value="mililitro">Mililitros (ml)</option>
                                <option value="unidad">Unidades (un)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Indicador en Vivo de Conversión --}}
                    <div class="alert alert-success py-2 px-3 mb-3 small d-flex align-items-center gap-2" id="entryConversionPreviewBox">
                        <i class="bi bi-info-circle-fill text-success fs-5"></i>
                        <span id="entryConversionPreviewText">Se ingresarán automáticamente: <strong>500 g</strong> al stock</span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="entry_cost_price" class="form-label small fw-semibold">Nuevo Precio Compra (opcional):</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="text" inputmode="numeric" name="cost_price" id="entry_cost_price" class="form-control currency-input" placeholder="0">
                            </div>
                        </div>
                        <div class="col-6">
                            <label for="entry_supplier_id" class="form-label small fw-semibold">Proveedor:</label>
                            <select name="supplier_id" id="entry_supplier_id" class="form-select form-select-sm">
                                <option value="">-- Opcional --</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="entry_invoice_number" class="form-label small fw-semibold">N° Factura / Remisión:</label>
                            <input type="text" name="invoice_number" id="entry_invoice_number" class="form-control form-control-sm" placeholder="Ej: FAC-1029">
                        </div>
                        <div class="col-6">
                            <label for="entry_notes" class="form-label small fw-semibold">Notas / Observaciones:</label>
                            <input type="text" name="notes" id="entry_notes" class="form-control form-control-sm" placeholder="Ej: Compra semanal">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-califit btn-sm px-3">
                        <i class="bi bi-check-circle me-1"></i> Guardar Entrada
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- MODAL 2: REGISTRAR MERMA O PÉRDIDA --}}
<div class="modal fade" id="wasteModal" tabindex="-1" aria-labelledby="wasteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('inventory.waste.store') }}">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white py-2">
                    <h6 class="modal-title fw-bold" id="wasteModalLabel">
                        <i class="bi bi-trash3 me-1"></i> Registrar Merma / Pérdida
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label for="waste_product_id" class="form-label small fw-semibold">Producto / Insumo:</label>
                        <select name="product_id" id="waste_product_id" class="form-select" required>
                            <option value="">-- Seleccionar --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->nombre }} (Stock: {{ $p->formatted_stock }} {{ $p->unidad_base }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="waste_quantity" class="form-label small fw-semibold">Cantidad a Deducir (en unidad base):</label>
                        <input type="number" name="quantity" id="waste_quantity" class="form-control" step="0.001" min="0.001" required>
                        <small class="text-muted">Gramos para insumos sólidos, ml para líquidos o unidades completas.</small>
                    </div>

                    <div class="mb-2">
                        <label for="waste_reason" class="form-label small fw-semibold">Motivo de la Merma:</label>
                        <input type="text" name="reason" id="waste_reason" class="form-control" placeholder="Ej: Producto dañado por cadena de frío, vencimiento, merma de cocción..." required>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3">
                        <i class="bi bi-trash3 me-1"></i> Confirmar Merma
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- MODAL 3: AJUSTE POR CONTEO FÍSICO --}}
<div class="modal fade" id="adjustmentModal" tabindex="-1" aria-labelledby="adjustmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('inventory.adjustment.store') }}">
            @csrf
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title fw-bold" id="adjustmentModalLabel">
                        <i class="bi bi-sliders me-1"></i> Ajuste por Conteo Físico Real
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label for="adj_product_id" class="form-label small fw-semibold">Producto / Insumo:</label>
                        <select name="product_id" id="adj_product_id" class="form-select" required onchange="onAdjProductChange()">
                            <option value="">-- Seleccionar --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" data-stock="{{ (float)$p->stock }}" data-unit="{{ $p->unidad_base }}">
                                    {{ $p->nombre }} (Stock Teórico: {{ $p->formatted_stock }} {{ $p->unidad_base }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="adj_real_stock" class="form-label small fw-semibold">Conteo Físico Real Encontrado:</label>
                        <input type="number" name="real_stock" id="adj_real_stock" class="form-control" step="0.001" min="0" required oninput="calculateAdjDiff()">
                        <div class="mt-1 small" id="adjDiffText">Diferencia: 0</div>
                    </div>

                    <div class="mb-2">
                        <label for="adj_reason" class="form-label small fw-semibold">Motivo del Ajuste:</label>
                        <input type="text" name="reason" id="adj_reason" class="form-control" placeholder="Ej: Conteo físico semanal, corrección de inventario" required>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-check-circle me-1"></i> Aplicar Ajuste Físico
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Buscador en tiempo real de inventario
    document.getElementById('inventorySearch').addEventListener('input', filterInventoryTable);

    // Filtros por botón
    document.querySelectorAll('.inventory-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.inventory-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterInventoryTable();
        });
    });

    calculateEntryConversion();
});

function filterInventoryTable() {
    const term = document.getElementById('inventorySearch').value.toLowerCase().trim();
    const activeType = document.querySelector('.inventory-filter-btn.active').dataset.type;

    document.querySelectorAll('.inventory-row').forEach(row => {
        const name = row.dataset.name;
        const sku = row.dataset.sku;
        const type = row.dataset.type;

        const matchesText = name.includes(term) || sku.includes(term);
        const matchesType = activeType === 'all' || type === activeType;

        if (matchesText && matchesType) {
            row.classList.remove('d-none');
        } else {
            row.classList.add('d-none');
        }
    });
}

function calculateEntryConversion() {
    const qty = parseFloat(document.getElementById('purchase_quantity').value) || 0;
    const unit = document.getElementById('purchase_unit').value;
    const sel = document.getElementById('entry_product_id');
    const opt = sel.options[sel.selectedIndex];
    const baseUnit = opt ? (opt.dataset.base || 'GRAMO') : 'GRAMO';

    let factor = 1;
    let explanation = '';

    if (unit === 'libra') {
        factor = 500;
        explanation = 'Factor oficial SaborCaliFit: 1 lb = 500 g';
    } else if (unit === 'kilo') {
        factor = 1000;
        explanation = 'Factor: 1 kg = 1000 g';
    } else if (unit === 'litro') {
        factor = 1000;
        explanation = 'Factor: 1 lt = 1000 ml';
    } else {
        factor = 1;
        explanation = 'Entrada directa 1 a 1';
    }

    const converted = qty * factor;
    const textBox = document.getElementById('entryConversionPreviewText');
    textBox.innerHTML = `Se ingresarán automáticamente al stock: <strong>${converted.toLocaleString('es-CO')} ${baseUnit}</strong> (${explanation})`;
}

function openEntryForProduct(productId, name, baseUnit) {
    const sel = document.getElementById('entry_product_id');
    sel.value = productId;
    calculateEntryConversion();
    const modal = new bootstrap.Modal(document.getElementById('entryModal'));
    modal.show();
}

function openWasteForProduct(productId, name, baseUnit) {
    const sel = document.getElementById('waste_product_id');
    sel.value = productId;
    const modal = new bootstrap.Modal(document.getElementById('wasteModal'));
    modal.show();
}

function openAdjustmentForProduct(productId, name, currentStock, baseUnit) {
    const sel = document.getElementById('adj_product_id');
    sel.value = productId;
    document.getElementById('adj_real_stock').value = currentStock;
    onAdjProductChange();
    const modal = new bootstrap.Modal(document.getElementById('adjustmentModal'));
    modal.show();
}

function onAdjProductChange() {
    calculateAdjDiff();
}

function calculateAdjDiff() {
    const sel = document.getElementById('adj_product_id');
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.dataset.stock) return;

    const theoretical = parseFloat(opt.dataset.stock) || 0;
    const real = parseFloat(document.getElementById('adj_real_stock').value) || 0;
    const diff = real - theoretical;
    const unit = opt.dataset.unit || '';

    const textEl = document.getElementById('adjDiffText');
    if (diff === 0) {
        textEl.textContent = `Diferencia: 0 ${unit} (Conteo exacto)`;
        textEl.className = 'mt-1 small text-success fw-bold';
    } else if (diff > 0) {
        textEl.textContent = `Diferencia: +${diff} ${unit} (Sobrante físico a favor)`;
        textEl.className = 'mt-1 small text-primary fw-bold';
    } else {
        textEl.textContent = `Diferencia: ${diff} ${unit} (Faltante físico / Merma no registrada)`;
        textEl.className = 'mt-1 small text-danger fw-bold';
    }
}
</script>
@endsection
