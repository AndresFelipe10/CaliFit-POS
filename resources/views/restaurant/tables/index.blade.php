@extends('layouts.app')

@section('title', 'Salón y Mesas | SaborCaliFit')

@section('content')
<div class="container-fluid px-2 px-md-3 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">
                <i class="bi bi-grid-3x3-gap-fill text-success me-2"></i>Salón y Mesas
            </h1>
            <p class="text-muted small mb-0">Gestión en tiempo real de mesas y comandas del salón.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('pos.index') }}" class="btn btn-outline-success btn-sm">
                <i class="bi bi-calculator me-1"></i> Ir al POS
            </a>
            <a href="{{ route('restaurant.dispatch.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-bicycle me-1"></i> Despacho / Domicilios
            </a>
        </div>
    </div>

    {{-- Resumen de Estados --}}
    @php
        $totalTables = $tables->count();
        $availableCount = $tables->where('status', 'available')->count();
        $occupiedCount = $tables->where('status', 'occupied')->count();
        $billedCount = $tables->where('status', 'billed')->count();
    @endphp
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2 rounded-3 text-center">
                <small class="text-muted fw-semibold">Total Mesas</small>
                <h5 class="fw-bold mb-0 text-dark">{{ $totalTables }}</h5>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2 rounded-3 text-center border-start border-4 border-success">
                <small class="text-muted fw-semibold">Libres</small>
                <h5 class="fw-bold mb-0 text-success">{{ $availableCount }}</h5>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2 rounded-3 text-center border-start border-4 border-danger">
                <small class="text-muted fw-semibold">Ocupadas</small>
                <h5 class="fw-bold mb-0 text-danger">{{ $occupiedCount }}</h5>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-2 rounded-3 text-center border-start border-4 border-warning">
                <small class="text-muted fw-semibold">En Cobro</small>
                <h5 class="fw-bold mb-0 text-warning">{{ $billedCount }}</h5>
            </div>
        </div>
    </div>

    {{-- Cuadrícula Compacta de Alta Densidad Táctil --}}
    <div class="row row-cols-2 row-cols-md-4 row-cols-xl-6 g-3">
        @forelse($tables as $table)
            @php
                $isAvailable = $table->status === 'available';
                $isOccupied = $table->status === 'occupied';
                $isBilled = $table->status === 'billed';
                $activeOrder = $table->activeOrder;

                $borderColor = $isAvailable ? '#10B981' : ($isOccupied ? '#EF4444' : '#F59E0B');
                $badgeBg = $isAvailable ? 'badge-status-available' : ($isOccupied ? 'badge-status-occupied' : 'badge-status-billed');
                $statusText = $isAvailable ? 'LIBRE' : ($isOccupied ? 'OCUPADA' : 'EN COBRO');
            @endphp
            <div class="col">
                <div class="card h-100 shadow-sm rounded-3 border-2 d-flex flex-column" 
                     style="border-color: {{ $borderColor }} !important; min-height: 210px;">
                    {{-- Encabezado de la tarjeta --}}
                    <div class="card-header bg-white border-0 p-2 d-flex justify-content-between align-items-center">
                        <span class="badge {{ $badgeBg }} rounded-pill px-2 py-1 fw-bold" style="font-size: 0.68rem;">
                            {{ $statusText }}
                        </span>
                        <span class="badge bg-light text-muted border" style="font-size: 0.68rem;" title="Capacidad">
                            <i class="bi bi-people-fill me-1"></i>{{ $activeOrder->guest_count ?? $table->capacity }}
                        </span>
                    </div>

                    {{-- Cuerpo de la tarjeta --}}
                    <div class="card-body p-2 text-center d-flex flex-column justify-content-center">
                        <h5 class="fw-bold text-dark mb-1 text-truncate" title="{{ $table->name }}">{{ $table->name }}</h5>

                        @if(($isOccupied || $isBilled) && $activeOrder)
                            <div class="bg-light rounded p-1 mb-1 text-start" style="font-size: 0.75rem;">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold text-secondary">{{ $activeOrder->order_number }}</span>
                                    <span class="text-muted"><i class="bi bi-clock"></i> {{ $activeOrder->created_at->diffForHumans(null, true) }}</span>
                                </div>
                                <div class="fw-black text-success mt-1" style="font-size: 0.88rem; color: #10B981 !important;">
                                    ${{ number_format($activeOrder->total, 0, ',', '.') }}
                                </div>
                            </div>
                        @else
                            <div class="text-success small py-2">
                                <i class="bi bi-check-circle fs-5 d-block"></i>
                                <span style="font-size: 0.75rem;">Mesa Disponible</span>
                            </div>
                        @endif
                    </div>

                    {{-- Acciones Rápidas (Touch Targets >= 44px) --}}
                    <div class="card-footer bg-white border-0 p-2 pt-0 mt-auto">
                        @if($isAvailable)
                            <button type="button" 
                                    class="btn btn-outline-success w-100 fw-bold d-flex align-items-center justify-content-center py-2" 
                                    style="min-height: 44px; font-size: 0.82rem;" 
                                    onclick="openTableModal({{ $table->id }}, '{{ addslashes($table->name) }}')">
                                <i class="bi bi-plus-circle me-1"></i> Abrir Mesa
                            </button>
                        @elseif($activeOrder)
                            <div class="vstack gap-1">
                                <a href="{{ route('restaurant.orders.show', $activeOrder) }}" 
                                   class="btn btn-sm btn-califit w-100 fw-bold d-flex align-items-center justify-content-center py-2" 
                                   style="min-height: 44px; font-size: 0.82rem;">
                                    <i class="bi bi-receipt me-1"></i> Ver / Tandas
                                </a>
                                <div class="d-flex gap-1">
                                    <form action="{{ route('restaurant.orders.pre-bill', $activeOrder) }}" method="POST" target="_blank" class="w-50" onsubmit="setTimeout(() => window.location.reload(), 600)">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning w-100 py-1" style="font-size: 0.72rem;" title="Generar Pre-cuenta en nueva pestaña">
                                            Pre-cuenta
                                        </button>
                                    </form>
                                    <a href="{{ route('restaurant.orders.show', $activeOrder) }}#cobro" class="btn btn-sm btn-outline-success w-50 py-1" style="font-size: 0.72rem;" title="Cobrar comanda">
                                        Cobrar
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 py-5 text-center text-muted">
                <i class="bi bi-grid display-4 d-block mb-2 text-secondary opacity-50"></i>
                <p>No hay mesas registradas en el salón.</p>
            </div>
        @endforelse
    </div>
</div>

{{-- MODAL PARA ABRIR MESA --}}
<div class="modal fade" id="openTableModal" tabindex="-1" aria-labelledby="openTableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="openTableForm" method="POST" action="">
            @csrf
            <input type="hidden" name="items" id="openTableItemsJson" value="[]">

            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white py-2.5 px-3">
                    <h6 class="modal-title fw-bold d-flex align-items-center" id="openTableModalLabel">
                        <i class="bi bi-door-open-fill text-success me-2 fs-5"></i>
                        <span>Apertura de Mesa: <span id="modalTableName" class="text-success fw-bold">Mesa</span></span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    {{-- Parámetros Principales de la Mesa --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label for="openTableWaiterName" class="form-label small fw-semibold text-dark">
                                <i class="bi bi-person-badge text-success me-1"></i>Mesero Responsable: <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="waiter_name" id="openTableWaiterName" 
                                   class="form-control form-control-sm fw-semibold" 
                                   value="{{ explode(' ', trim(auth()->user()->name ?? ''))[0] ?? 'Mesero' }}" 
                                   placeholder="Nombre del mesero" required>
                        </div>
                        <div class="col-md-3">
                            <label for="guest_count" class="form-label small fw-semibold text-dark">
                                <i class="bi bi-people text-primary me-1"></i>N° Comensales: <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="guest_count" id="guest_count" class="form-control form-control-sm text-center fw-bold" value="2" min="1" max="99" required>
                        </div>
                        <div class="col-md-4">
                            <label for="order_notes" class="form-label small fw-semibold text-dark">
                                <i class="bi bi-chat-left-text text-secondary me-1"></i>Notas (opcional):
                            </label>
                            <input type="text" name="notes" id="order_notes" class="form-control form-control-sm" placeholder="Ej: Terraza, frecuente...">
                        </div>
                    </div>

                    <hr class="my-3 border-secondary opacity-25">

                    {{-- Registro Opcional de Primera Tanda de Platos --}}
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-bold text-dark mb-0">
                            <i class="bi bi-cart-plus text-success me-1"></i>Registrar Platos / Suplementos (Opcional - Tanda Inicial):
                        </label>
                        <span class="badge bg-light text-secondary border small">Primera Comanda</span>
                    </div>

                    <div class="row g-2 mb-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold text-muted mb-1">Buscar y Seleccionar Producto:</label>
                            <div class="input-group input-group-sm mb-1">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" id="openTableProductSearch" class="form-control" placeholder="Buscar por nombre..." oninput="filterOpenTableProducts()" autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" onclick="clearOpenTableProductSearch()" title="Limpiar"><i class="bi bi-x"></i></button>
                            </div>
                            <select id="openTableProductSelect" class="form-select form-select-sm" onchange="onOpenTableProductChange()">
                                <option value="">-- Seleccionar de la lista --</option>
                                @if(isset($products))
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}" data-name="{{ $p->nombre }}" data-price="{{ $p->precio_venta }}" data-is-dish="{{ ($p->isDish() || $p->tipo_producto === 'PLATO') ? '1' : '0' }}">
                                            {{ $p->nombre }} - ${{ number_format($p->precio_venta, 0, ',', '.') }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold text-muted mb-1">Cantidad:</label>
                            <input type="number" id="openTableProductQty" class="form-control form-control-sm text-center fw-bold" value="1" min="1" step="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">Notas / Prep.:</label>
                            <input type="text" id="openTableProductNotes" class="form-control form-control-sm" placeholder="Ej: Término 3/4, sin sal">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-success btn-sm w-100 py-1.5 fw-semibold" onclick="addOpenTableStagedItem()">
                                <i class="bi bi-plus-lg me-1"></i> Añadir
                            </button>
                        </div>
                    </div>

                    {{-- Adiciones del plato seleccionado --}}
                    @if(isset($adiciones) && $adiciones->count() > 0)
                        <div id="openTableAdditionsSection" class="border rounded-3 p-2 mb-3 bg-light d-none">
                            <label class="form-label small fw-semibold text-success mb-2">Adiciones para este plato:</label>
                            <div class="row row-cols-2 g-2" style="max-height: 140px; overflow-y: auto;">
                                @foreach($adiciones as $add)
                                    <div class="col">
                                        <div class="d-flex justify-content-between align-items-center bg-white p-1.5 rounded border">
                                            <div>
                                                <div class="small fw-semibold text-dark">{{ $add->nombre }}</div>
                                                <small class="text-success fw-bold">+${{ number_format($add->precio, 0, ',', '.') }}</small>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;" onclick="adjustOpenTableAddQty({{ $add->id }}, -1)">-</button>
                                                <span class="small fw-bold px-1 open-table-add-qty" id="openTableAddQty_{{ $add->id }}" data-id="{{ $add->id }}" data-name="{{ $add->nombre }}" data-price="{{ $add->precio }}">0</span>
                                                <button type="button" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;" onclick="adjustOpenTableAddQty({{ $add->id }}, 1)">+</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Tabla de ítems a registrar --}}
                    <div class="table-responsive border rounded-3 bg-white mb-2" style="max-height: 180px; overflow-y: auto;">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light small sticky-top">
                                <tr>
                                    <th>Ítem</th>
                                    <th class="text-center" style="width: 15%;">Cant.</th>
                                    <th style="width: 30%;">Adiciones</th>
                                    <th style="width: 25%;">Notas</th>
                                    <th class="text-end" style="width: 10%;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="openTableStagedTbody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-2.5 small">Sin platos agregados (se abrirá la mesa vacía).</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2 px-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-califit btn-sm px-4 fw-bold shadow-sm" id="btnSubmitOpenTable">
                        <i class="bi bi-check-circle me-1"></i> Abrir Mesa e Iniciar Comanda
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let openTableStagedItems = [];
let allOpenTableOptions = [];

document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('openTableProductSelect');
    if (sel) {
        allOpenTableOptions = Array.from(sel.querySelectorAll('option')).map(opt => ({
            value: opt.value,
            text: opt.textContent.trim(),
            name: opt.dataset.name || opt.textContent.split(' - ')[0].trim(),
            price: opt.dataset.price || '0',
            isDish: opt.dataset.isDish || '0'
        }));
    }
});

function openTableModal(tableId, tableName) {
    // Limpieza de backdrops y estilos residuales
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');

    const submitBtn = document.getElementById('btnSubmitOpenTable');
    if (submitBtn) {
        submitBtn.disabled = false;
        if (submitBtn.dataset.originalHtml) {
            submitBtn.innerHTML = submitBtn.dataset.originalHtml;
        }
    }

    document.getElementById('modalTableName').textContent = tableName;
    const form = document.getElementById('openTableForm');
    form.action = `/restaurant/tables/${tableId}/open`;
    
    // Resetear formulario
    openTableStagedItems = [];
    document.getElementById('openTableItemsJson').value = '[]';
    document.getElementById('guest_count').value = '2';
    document.getElementById('order_notes').value = '';
    
    const waiterInput = document.getElementById('openTableWaiterName');
    if (waiterInput && !waiterInput.value) {
        waiterInput.value = "{{ explode(' ', trim(auth()->user()->name ?? ''))[0] ?? 'Mesero' }}";
    }
    
    clearOpenTableProductSearch();
    renderOpenTableStagedTable();

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('openTableModal'));
    modal.show();
}

function filterOpenTableProducts() {
    const searchInput = document.getElementById('openTableProductSearch');
    const query = (searchInput ? searchInput.value : '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
    const sel = document.getElementById('openTableProductSelect');
    if (!sel || !allOpenTableOptions.length) return;

    sel.innerHTML = '';
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = '-- Seleccionar de la lista --';
    sel.appendChild(defaultOpt);

    let matchCount = 0;
    let singleMatchValue = '';

    allOpenTableOptions.forEach(item => {
        if (!item.value) return;
        const normName = item.name.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        if (normName.includes(query)) {
            const opt = document.createElement('option');
            opt.value = item.value;
            opt.textContent = item.text;
            opt.dataset.name = item.name;
            opt.dataset.price = item.price;
            opt.dataset.isDish = item.isDish;
            sel.appendChild(opt);
            matchCount++;
            singleMatchValue = item.value;
        }
    });

    if (query !== '' && matchCount === 1) {
        sel.value = singleMatchValue;
        onOpenTableProductChange(false);
    }
}

function clearOpenTableProductSearch() {
    const searchInput = document.getElementById('openTableProductSearch');
    if (searchInput) searchInput.value = '';
    filterOpenTableProducts();
    const sel = document.getElementById('openTableProductSelect');
    if (sel) {
        sel.value = '';
        onOpenTableProductChange(false);
    }
}

function onOpenTableProductChange(syncInput = true) {
    const sel = document.getElementById('openTableProductSelect');
    const opt = sel.options[sel.selectedIndex];
    const isDish = opt ? opt.dataset.isDish === '1' : false;
    const sec = document.getElementById('openTableAdditionsSection');

    if (syncInput && opt && opt.value) {
        const searchInput = document.getElementById('openTableProductSearch');
        if (searchInput) {
            searchInput.value = opt.dataset.name || opt.text.split(' - ')[0].trim();
        }
    }

    if (sec) {
        if (isDish) {
            sec.classList.remove('d-none');
        } else {
            sec.classList.add('d-none');
            document.querySelectorAll('.open-table-add-qty').forEach(el => el.textContent = '0');
        }
    }
}

function adjustOpenTableAddQty(id, delta) {
    const el = document.getElementById(`openTableAddQty_${id}`);
    if (!el) return;
    let qty = parseInt(el.textContent) || 0;
    qty = Math.max(0, qty + delta);
    el.textContent = qty;
}

function addOpenTableStagedItem() {
    const sel = document.getElementById('openTableProductSelect');
    const pid = parseInt(sel.value);
    if (!pid) {
        califitNotify('Selecciona un producto o plato para añadir a la comanda inicial.', 'warning', 'Producto Requerido');
        return;
    }

    const opt = sel.options[sel.selectedIndex];
    const name = opt.dataset.name || opt.text.split(' - ')[0].trim();
    const qty = parseFloat(document.getElementById('openTableProductQty').value) || 1;
    const notes = document.getElementById('openTableProductNotes').value.trim();

    const adds = [];
    document.querySelectorAll('.open-table-add-qty').forEach(el => {
        const q = parseInt(el.textContent) || 0;
        if (q > 0) {
            adds.push({
                addition_id: parseInt(el.dataset.id),
                name: el.dataset.name,
                quantity: q
            });
        }
    });

    openTableStagedItems.push({
        product_id: pid,
        name: name,
        quantity: qty,
        notes: notes || null,
        additions: adds
    });

    // Resetear formulario
    clearOpenTableProductSearch();
    document.getElementById('openTableProductQty').value = 1;
    document.getElementById('openTableProductNotes').value = '';
    document.querySelectorAll('.open-table-add-qty').forEach(el => el.textContent = '0');
    const sec = document.getElementById('openTableAdditionsSection');
    if (sec) sec.classList.add('d-none');

    renderOpenTableStagedTable();
}

function removeOpenTableStagedItem(idx) {
    openTableStagedItems.splice(idx, 1);
    renderOpenTableStagedTable();
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function renderOpenTableStagedTable() {
    const tbody = document.getElementById('openTableStagedTbody');
    tbody.innerHTML = '';

    if (openTableStagedItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2.5 small">Sin platos agregados (se abrirá la mesa vacía).</td></tr>';
        document.getElementById('openTableItemsJson').value = '[]';
        return;
    }

    openTableStagedItems.forEach((item, idx) => {
        const tr = document.createElement('tr');
        const addsText = item.additions && item.additions.length > 0 
            ? item.additions.map(a => `<span class="badge bg-light text-dark border">+${escapeHtml(a.name)} (x${escapeHtml(a.quantity)})</span>`).join(' ') 
            : '<span class="text-muted small">Ninguna</span>';
        
        tr.innerHTML = `
            <td><strong class="text-dark small">${escapeHtml(item.name)}</strong></td>
            <td class="text-center"><span class="badge bg-dark-subtle text-dark">${escapeHtml(item.quantity)}</span></td>
            <td><small>${addsText}</small></td>
            <td><small class="text-muted">${escapeHtml(item.notes || '-')}</small></td>
            <td class="text-end">
                <button type="button" class="btn btn-outline-danger btn-sm p-0 rounded-circle d-flex align-items-center justify-content-center ms-auto" style="width: 24px; height: 24px;" onclick="removeOpenTableStagedItem(${idx})" title="Eliminar plato">
                    <i class="bi bi-trash" style="font-size: 0.7rem;"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('openTableItemsJson').value = JSON.stringify(openTableStagedItems);
}

document.getElementById('openTableForm').addEventListener('submit', function() {
    document.getElementById('openTableItemsJson').value = JSON.stringify(openTableStagedItems);
});

@if(session('auto_print_kitchen_url'))
document.addEventListener('DOMContentLoaded', function() {
    const printUrl = @json(session('auto_print_kitchen_url'));
    if (printUrl) {
        const printWindow = window.open(printUrl, '_blank', 'width=340,height=620,menubar=no,toolbar=no,location=no,status=no');
        if (printWindow) {
            printWindow.focus();
        }
    }
});
@endif
</script>
@endsection
