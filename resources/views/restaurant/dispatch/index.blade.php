@extends('layouts.app')

@section('title', 'Despacho y Domicilios | SaborCaliFit')

@section('content')
<div class="container-fluid px-2 px-md-3 py-3">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">
                <i class="bi bi-bicycle text-success me-2"></i>Tablero de Despacho y Domicilios
            </h1>
            <p class="text-muted small mb-0">Control operativo de pedidos para llevar y entregas a domicilio.</p>
        </div>
        <div>
            <button type="button" class="btn btn-califit px-3 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#newDeliveryModal">
                <i class="bi bi-plus-lg me-1"></i> Nuevo Pedido Domicilio / Para Llevar
            </button>
        </div>
    </div>

    {{-- TABLERO KANBAN EN 3 COLUMNAS --}}
    <div class="row g-3">
        {{-- COLUMNA 1: EN COCINA / PREPARACIÓN --}}
        <div class="col-lg-4">
            <div class="card bg-light border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-fire text-danger me-1"></i> 1. En Cocina / Preparación
                    </span>
                    <span class="badge bg-danger rounded-pill">{{ $inKitchen->count() }}</span>
                </div>
                <div class="card-body p-2 vstack gap-2" style="min-height: 480px; max-height: calc(100vh - 200px); overflow-y: auto;">
                    @forelse($inKitchen as $order)
                        @include('restaurant.dispatch._card', ['order' => $order, 'nextStatus' => 'dispatched', 'nextLabel' => 'Despachar con Repartidor ->'])
                    @empty
                        <div class="text-center text-muted py-5 small">
                            <i class="bi bi-check2-circle fs-3 text-secondary opacity-50 d-block mb-1"></i>
                            No hay pedidos en preparación.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- COLUMNA 2: EN DESPACHO / CON REPARTIDOR --}}
        <div class="col-lg-4">
            <div class="card bg-light border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-bicycle text-primary me-1"></i> 2. En Camino / Despachado
                    </span>
                    <span class="badge bg-primary rounded-pill">{{ $dispatched->count() }}</span>
                </div>
                <div class="card-body p-2 vstack gap-2" style="min-height: 480px; max-height: calc(100vh - 200px); overflow-y: auto;">
                    @forelse($dispatched as $order)
                        @include('restaurant.dispatch._card', ['order' => $order, 'nextStatus' => 'delivered', 'nextLabel' => 'Marcar Entregado ->'])
                    @empty
                        <div class="text-center text-muted py-5 small">
                            <i class="bi bi-bicycle fs-3 text-secondary opacity-50 d-block mb-1"></i>
                            No hay pedidos en ruta actualmente.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- COLUMNA 3: ENTREGADO (PENDIENTE LIQUIDAR) --}}
        <div class="col-lg-4">
            <div class="card bg-light border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-check-all text-success me-1"></i> 3. Entregado (Por Cobrar)
                    </span>
                    <span class="badge bg-success rounded-pill">{{ $delivered->count() }}</span>
                </div>
                <div class="card-body p-2 vstack gap-2" style="min-height: 480px; max-height: calc(100vh - 200px); overflow-y: auto;">
                    @forelse($delivered as $order)
                        @include('restaurant.dispatch._card', ['order' => $order, 'nextStatus' => null, 'nextLabel' => 'Cobrar / Liquidar'])
                    @empty
                        <div class="text-center text-muted py-5 small">
                            <i class="bi bi-wallet2 fs-3 text-secondary opacity-50 d-block mb-1"></i>
                            No hay pedidos pendientes de liquidación.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL NUEVO PEDIDO A DOMICILIO O PARA LLEVAR --}}
<div class="modal fade" id="newDeliveryModal" tabindex="-1" aria-labelledby="newDeliveryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold" id="newDeliveryModalLabel">
                    <i class="bi bi-bicycle text-success me-1"></i> Registrar Pedido de Despacho
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3">
                    <div class="col-12">
                        <label class="form-label small fw-semibold text-success d-flex justify-content-between align-items-center mb-1">
                            <span><i class="bi bi-person-check-fill me-1"></i> Cliente Registrado (Selector Unificado):</span>
                            <span class="text-muted fw-normal" style="font-size: 0.72rem;">Autocompleta nombre, teléfono y dirección</span>
                        </label>
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="dispatchCustomerSearch" class="form-control" placeholder="Buscar por nombre, cédula o teléfono..." oninput="filterDispatchCustomerSelect(this.value)" autocomplete="off">
                        </div>
                        <div class="input-group input-group-sm">
                            <select id="dispatchCustomerSelect" class="form-select form-select-sm border-success-subtle" onchange="onDispatchCustomerSelect()">
                                <option value="">-- Seleccionar cliente registrado o ingresar datos manualmente abajo --</option>
                                @if(isset($customers))
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}"
                                                data-name="{{ $c->name }}"
                                                data-doc="{{ $c->document ?? '' }}"
                                                data-phone="{{ $c->phone ?? '' }}"
                                                data-address="{{ $c->address ?? '' }}">
                                            {{ $c->name }} {{ $c->document ? '('.$c->document.')' : '' }} {{ $c->phone ? '| Tel: '.$c->phone : '' }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <button type="button" class="btn btn-outline-success" onclick="openDispatchQuickCustomerModal()" title="Crear Cliente Rápido">
                                <i class="bi bi-person-plus-fill"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Canal de Pedido:</label>
                        <select id="dispatchOrderType" class="form-select form-select-sm" onchange="toggleAddressField()">
                            <option value="delivery">Domicilio (Con entrega)</option>
                            <option value="takeout">Para Llevar / Recoger</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Nombre Cliente: <span class="text-danger">*</span></label>
                        <input type="text" id="dispatchCustName" class="form-control form-control-sm" placeholder="Ej: Andrea Gómez" required oninput="this.classList.remove('is-invalid')">
                        <div class="invalid-feedback small">El nombre del cliente es obligatorio.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Teléfono / WhatsApp: <span class="text-danger">*</span></label>
                        <input type="text" id="dispatchPhone" class="form-control form-control-sm" placeholder="Ej: 312 770 4455" required oninput="this.classList.remove('is-invalid')">
                        <div class="invalid-feedback small">El teléfono es obligatorio.</div>
                    </div>
                    <div class="col-md-8" id="dispatchAddressCol">
                        <label class="form-label small fw-semibold">Dirección de Entrega: <span class="text-danger">*</span></label>
                        <input type="text" id="dispatchAddress" class="form-control form-control-sm" placeholder="Ej: Cra 105 # 45-20 Apto 302" oninput="this.classList.remove('is-invalid')">
                        <div class="invalid-feedback small">La dirección de entrega es obligatoria para domicilios.</div>
                    </div>
                    <div class="col-md-4" id="dispatchDeliveryFeeCol">
                        <label class="form-label small fw-semibold">Flete Domicilio ($):</label>
                        <input type="number" id="dispatchDeliveryFee" class="form-control form-control-sm" value="0" min="0" step="500" placeholder="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Instrucciones / Observaciones:</label>
                        <input type="text" id="dispatchNotes" class="form-control form-control-sm" placeholder="Ej: Pago contraentrega con billete de $50.000">
                    </div>
                </div>

                <hr class="my-2 border-secondary opacity-25">

                {{-- Selector de Platos --}}
                <label class="form-label small fw-semibold text-dark">Agregar Platos al Pedido:</label>
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="dispatchProductSearch" class="form-control" placeholder="Buscar por nombre..." oninput="filterDispatchProducts()" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" onclick="clearDispatchProductSearch()" title="Limpiar"><i class="bi bi-x"></i></button>
                        </div>
                        <select id="dispatchProductSelect" class="form-select form-select-sm" onchange="onDispatchProductChange()">
                            <option value="">-- Seleccionar Plato o Suplemento --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" data-name="{{ $p->nombre }}" data-price="{{ $p->precio_venta }}" data-is-dish="{{ ($p->isDish() || $p->tipo_producto === 'PLATO') ? '1' : '0' }}">
                                    {{ $p->nombre }} - ${{ number_format($p->precio_venta, 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" id="dispatchProductQty" class="form-control form-control-sm text-center" value="1" min="1">
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="button" class="btn btn-outline-success btn-sm w-100" onclick="addDispatchStagedItem()">
                            <i class="bi bi-plus-lg me-1"></i> Añadir al Pedido
                        </button>
                    </div>
                </div>

                {{-- Adiciones del plato --}}
                <div id="dispatchAdditionsSection" class="border rounded p-2 mb-3 bg-light d-none">
                    <label class="form-label small fw-semibold text-success mb-2">Adiciones para este plato:</label>
                    <div class="row row-cols-2 g-2">
                        @foreach($adiciones as $add)
                            <div class="col">
                                <div class="d-flex justify-content-between align-items-center bg-white p-1.5 rounded border">
                                    <div>
                                        <div class="small fw-semibold text-dark">{{ $add->nombre }}</div>
                                        <small class="text-success fw-bold">+${{ number_format($add->precio, 0, ',', '.') }}</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;" onclick="adjustDispatchAddQty({{ $add->id }}, -1)">-</button>
                                        <span class="small fw-bold px-1 dispatch-add-qty" id="dispatchAddQty_{{ $add->id }}" data-id="{{ $add->id }}" data-name="{{ $add->nombre }}">0</span>
                                        <button type="button" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;" onclick="adjustDispatchAddQty({{ $add->id }}, 1)">+</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Tabla de ítems a empacar --}}
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Ítem</th>
                                <th class="text-center">Cant.</th>
                                <th>Adiciones</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="dispatchStagedTbody">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-2 small">Aún no hay platos agregados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-califit btn-sm px-4" id="btnSubmitDispatch" onclick="submitDispatchOrder()">
                    <i class="bi bi-check-circle me-1"></i> Guardar y Despachar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let dispatchStagedItems = [];

function toggleAddressField() {
    const orderTypeSelect = document.getElementById('dispatchOrderType');
    const type = orderTypeSelect ? orderTypeSelect.value : 'delivery';
    const addrCol = document.getElementById('dispatchAddressCol');
    const feeCol = document.getElementById('dispatchDeliveryFeeCol');
    const feeInput = document.getElementById('dispatchDeliveryFee');
    const addrInput = document.getElementById('dispatchAddress');

    if (type === 'takeout') {
        if (addrCol) addrCol.classList.add('d-none');
        if (feeCol) feeCol.classList.add('d-none');
        if (feeInput) {
            feeInput.value = '0';
            feeInput.disabled = true;
        }
        if (addrInput) {
            addrInput.classList.remove('is-invalid');
        }
    } else {
        if (addrCol) addrCol.classList.remove('d-none');
        if (feeCol) feeCol.classList.remove('d-none');
        if (feeInput) {
            feeInput.disabled = false;
        }
    }
}

let allDispatchOptions = [];

document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('dispatchProductSelect');
    if (sel) {
        allDispatchOptions = Array.from(sel.querySelectorAll('option')).map(opt => ({
            value: opt.value,
            text: opt.textContent.trim(),
            name: opt.dataset.name || opt.textContent.split(' - ')[0].trim(),
            price: opt.dataset.price || '0',
            isDish: opt.dataset.isDish || '0'
        }));
    }

    const orderTypeSelect = document.getElementById('dispatchOrderType');
    if (orderTypeSelect) {
        orderTypeSelect.addEventListener('change', toggleAddressField);
    }

    const modalEl = document.getElementById('newDeliveryModal');
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', () => {
            toggleAddressField();
        });
    }

    toggleAddressField();
});

function filterDispatchProducts() {
    const searchInput = document.getElementById('dispatchProductSearch');
    const query = (searchInput ? searchInput.value : '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
    const sel = document.getElementById('dispatchProductSelect');
    if (!sel || !allDispatchOptions.length) return;

    sel.innerHTML = '';
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = '-- Seleccionar Plato o Suplemento --';
    sel.appendChild(defaultOpt);

    let matchCount = 0;
    let singleMatchValue = '';

    allDispatchOptions.forEach(item => {
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
        onDispatchProductChange(false);
    }
}

function clearDispatchProductSearch() {
    const searchInput = document.getElementById('dispatchProductSearch');
    if (searchInput) searchInput.value = '';
    filterDispatchProducts();
    const sel = document.getElementById('dispatchProductSelect');
    if (sel) {
        sel.value = '';
        onDispatchProductChange(false);
    }
}

function onDispatchProductChange(syncInput = true) {
    const sel = document.getElementById('dispatchProductSelect');
    const opt = sel.options[sel.selectedIndex];
    const isDish = opt ? opt.dataset.isDish === '1' : false;
    const sec = document.getElementById('dispatchAdditionsSection');

    if (syncInput && opt && opt.value) {
        const searchInput = document.getElementById('dispatchProductSearch');
        if (searchInput) {
            searchInput.value = opt.dataset.name || opt.text.split(' - ')[0].trim();
        }
    }

    if (isDish) {
        sec.classList.remove('d-none');
    } else {
        sec.classList.add('d-none');
        document.querySelectorAll('.dispatch-add-qty').forEach(el => el.textContent = '0');
    }
}

function adjustDispatchAddQty(id, delta) {
    const el = document.getElementById(`dispatchAddQty_${id}`);
    if (!el) return;
    let qty = parseInt(el.textContent) || 0;
    qty = Math.max(0, qty + delta);
    el.textContent = qty;
}

function onDispatchCustomerSelect() {
    const sel = document.getElementById('dispatchCustomerSelect');
    if (!sel || !sel.value) return;
    const opt = sel.options[sel.selectedIndex];
    if (opt) {
        const name = opt.getAttribute('data-name') || '';
        const phone = opt.getAttribute('data-phone') || '';
        const address = opt.getAttribute('data-address') || '';

        const nameInput = document.getElementById('dispatchCustName');
        const phoneInput = document.getElementById('dispatchPhone');
        const addrInput = document.getElementById('dispatchAddress');

        if (name && nameInput) {
            nameInput.value = name;
            nameInput.classList.remove('is-invalid');
        }
        if (phone && phoneInput) {
            phoneInput.value = phone;
            phoneInput.classList.remove('is-invalid');
        }
        if (address && addrInput) {
            addrInput.value = address;
            addrInput.classList.remove('is-invalid');
        }
    }
}

function addDispatchStagedItem() {
    const sel = document.getElementById('dispatchProductSelect');
    const pid = parseInt(sel.value);
    if (!pid) {
        califitNotify('Por favor selecciona un producto o plato para agregar al pedido.', 'warning', 'Plato Requerido');
        return;
    }

    const opt = sel.options[sel.selectedIndex];
    const name = opt.text.split(' - ')[0];
    const qty = parseFloat(document.getElementById('dispatchProductQty').value) || 1;

    const adds = [];
    document.querySelectorAll('.dispatch-add-qty').forEach(el => {
        const q = parseInt(el.textContent) || 0;
        if (q > 0) {
            adds.push({
                addition_id: parseInt(el.dataset.id),
                name: el.dataset.name,
                quantity: q
            });
        }
    });

    dispatchStagedItems.push({
        product_id: pid,
        name: name,
        quantity: qty,
        additions: adds
    });

    clearDispatchProductSearch();
    document.getElementById('dispatchProductQty').value = 1;
    document.querySelectorAll('.dispatch-add-qty').forEach(el => el.textContent = '0');
    document.getElementById('dispatchAdditionsSection').classList.add('d-none');

    renderDispatchStagedTable();
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

function renderDispatchStagedTable() {
    const tbody = document.getElementById('dispatchStagedTbody');
    tbody.innerHTML = '';

    if (dispatchStagedItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2 small">Aún no hay platos agregados.</td></tr>';
        return;
    }

    dispatchStagedItems.forEach((item, index) => {
        const tr = document.createElement('tr');
        const addsStr = item.additions.map(a => `+ ${escapeHtml(a.name)} (x${escapeHtml(a.quantity)})`).join(', ') || '-';
        tr.innerHTML = `
            <td class="fw-semibold">${escapeHtml(item.name)}</td>
            <td class="text-center fw-bold">${escapeHtml(item.quantity)}</td>
            <td class="small text-muted">${addsStr}</td>
            <td class="text-end">
                <button type="button" class="btn btn-link text-danger p-0" onclick="removeDispatchStagedItem(${index})"><i class="bi bi-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function removeDispatchStagedItem(index) {
    dispatchStagedItems.splice(index, 1);
    renderDispatchStagedTable();
}

async function submitDispatchOrder() {
    const custNameInput = document.getElementById('dispatchCustName');
    const phoneInput = document.getElementById('dispatchPhone');
    const addressInput = document.getElementById('dispatchAddress');

    const custName = custNameInput.value.trim();
    const phone = phoneInput.value.trim();
    const orderType = document.getElementById('dispatchOrderType').value;
    const address = addressInput.value.trim();
    const fee = orderType === 'takeout' ? 0 : (parseFloat(document.getElementById('dispatchDeliveryFee').value) || 0);
    const notes = document.getElementById('dispatchNotes').value.trim();

    let hasError = false;
    if (!custName) {
        custNameInput.classList.add('is-invalid');
        hasError = true;
    }
    if (!phone) {
        phoneInput.classList.add('is-invalid');
        hasError = true;
    }
    if (orderType === 'delivery' && !address) {
        addressInput.classList.add('is-invalid');
        hasError = true;
    }

    if (hasError) {
        califitNotify('Por favor completa los campos requeridos marcados en rojo.', 'warning', 'Datos Obligatorios');
        return;
    }

    if (dispatchStagedItems.length === 0) {
        califitNotify('Debes incluir al menos un producto o plato en el pedido para despachar.', 'warning', 'Pedido Vacío');
        return;
    }

    const payload = {
        order_type: orderType,
        customer_name: custName,
        phone: phone,
        address: address || null,
        delivery_fee: fee,
        notes: notes || null,
        items: dispatchStagedItems.map(i => ({
            product_id: i.product_id,
            quantity: i.quantity,
            additions: i.additions.map(a => ({
                addition_id: a.addition_id,
                quantity: a.quantity
            }))
        }))
    };

    const btn = document.getElementById('btnSubmitDispatch');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creando...';

    try {
        const res = await fetch("{{ route('restaurant.dispatch.store') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (res.ok && data.success) {
            window.open(data.print_url || `/prints/orders/${data.order_id}/kitchen`, '_blank', 'width=450,height=680');
            location.reload();
        } else {
            califitNotify('Error: ' + (data.message || JSON.stringify(data.errors || data)), 'danger', 'Error al Guardar');
        }
    } catch (e) {
        console.error(e);
        califitNotify('Error en la comunicación con el servidor.', 'danger', 'Error de Red');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Guardar y Despachar';
    }
}

async function advanceOrderStatus(orderId, newStatus, btn) {
    if (btn) {
        if (btn.disabled) return;
        btn.disabled = true;
        btn.dataset.originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Procesando...';
    }

    try {
        const res = await fetch(`/restaurant/orders/${orderId}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': "{{ csrf_token() }}",
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ status: newStatus })
        });

        const data = await res.json();
        if (res.ok && data.success) {
            location.reload();
        } else {
            califitNotify('Error al actualizar estado: ' + (data.message || ''), 'danger', 'Error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.originalHtml;
            }
        }
    } catch (e) {
        console.error(e);
        califitNotify('Error al comunicar cambio de estado con el servidor.', 'danger', 'Error de Red');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalHtml;
        }
    }
}
window.updateOrderStatus = advanceOrderStatus;

function filterDispatchCustomerSelect(query) {
    const sel = document.getElementById('dispatchCustomerSelect');
    if (!sel) return;
    const q = (query || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
    let firstMatched = -1;
    for (let i = 0; i < sel.options.length; i++) {
        const opt = sel.options[i];
        const text = (opt.text || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        const doc = (opt.dataset.doc || '').toLowerCase();
        const phone = (opt.dataset.phone || '').toLowerCase();
        const matches = text.includes(q) || doc.includes(q) || phone.includes(q);
        opt.style.display = matches ? '' : 'none';
        if (matches && firstMatched === -1 && q !== '') {
            firstMatched = i;
        }
    }
    if (firstMatched !== -1) {
        sel.selectedIndex = firstMatched;
        onDispatchCustomerSelect();
    }
}

function openDispatchQuickCustomerModal() {
    const form = document.getElementById('dispatchQuickCustomerForm');
    if (form) form.reset();
    const modal = new bootstrap.Modal(document.getElementById('dispatchQuickCustomerModal'));
    modal.show();
}

async function submitDispatchQuickCustomer(e) {
    e.preventDefault();
    const name = document.getElementById('dispatchQuickCustName').value.trim();
    const doc = document.getElementById('dispatchQuickCustDoc').value.trim();
    const phone = document.getElementById('dispatchQuickCustPhone').value.trim();
    const address = document.getElementById('dispatchQuickCustAddress').value.trim();

    if (!name) {
        califitNotify('El nombre es obligatorio.', 'warning', 'Validación');
        return;
    }

    const btn = document.getElementById('btnSubmitDispatchQuickCustomer');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

    try {
        const res = await fetch("{{ route('customers.store') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': "{{ csrf_token() }}",
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                name: name,
                document: doc || null,
                document_number: doc || null,
                phone: phone || null,
                address: address || null
            })
        });

        const data = await res.json();
        if (res.ok && data.success && data.customer) {
            const newCust = data.customer;
            const sel = document.getElementById('dispatchCustomerSelect');
            if (sel) {
                const optText = `${newCust.name} (${newCust.document_number || newCust.document || 'S/D'}) | Tel: ${newCust.phone || 'S/T'}`;
                const newOpt = new Option(optText, newCust.id, true, true);
                newOpt.dataset.name = newCust.name;
                newOpt.dataset.doc = newCust.document_number || newCust.document || '';
                newOpt.dataset.phone = newCust.phone || '';
                newOpt.dataset.address = newCust.address || '';
                sel.add(newOpt);
                sel.value = newCust.id;
                onDispatchCustomerSelect();
            }

            const modalEl = document.getElementById('dispatchQuickCustomerModal');
            const bsModal = bootstrap.Modal.getInstance(modalEl);
            if (bsModal) bsModal.hide();

            setTimeout(() => {
                if (document.querySelector('#newDeliveryModal.show')) {
                    document.body.classList.add('modal-open');
                }
            }, 300);

            califitNotify(`Cliente '${newCust.name}' registrado exitosamente.`, 'success', 'Cliente Registrado');
        } else {
            const errMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error al registrar cliente');
            califitNotify(errMsg, 'danger', 'Error');
        }
    } catch (err) {
        console.error(err);
        califitNotify('Error de conexión al registrar cliente.', 'danger', 'Error de Red');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Guardar y Asignar';
    }
}

async function printDispatchTicket(orderId, fallbackUrl) {
    try {
        const res = await fetch('/print-jobs/enqueue', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
            },
            body: JSON.stringify({ tipo: 'despacho', id: orderId })
        });
        const data = await res.json();
        if (data.success) {
            califitNotify('Tirilla de despacho enviada a la impresora térmica POS', 'success', 'Impresora 80mm');
        } else {
            window.open(fallbackUrl, '_blank', 'width=450,height=680');
        }
    } catch (err) {
        window.open(fallbackUrl, '_blank', 'width=450,height=680');
    }
}
</script>

{{-- Modal Rápido de Cliente para Despachos --}}
<div class="modal fade" id="dispatchQuickCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form id="dispatchQuickCustomerForm" onsubmit="submitDispatchQuickCustomer(event)" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            @csrf
            <div class="modal-header py-2 px-3 text-white" style="background-color: #111827; border-bottom: 3px solid #10B981;">
                <h6 class="modal-title fw-bold mb-0">
                    <i class="bi bi-person-plus text-success me-1"></i>Nuevo Cliente Rápido
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3 bg-white">
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark mb-1">Nombre: <span class="text-danger">*</span></label>
                    <input type="text" id="dispatchQuickCustName" class="form-control form-control-sm" required placeholder="Nombre completo">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark mb-1">Cédula / Documento:</label>
                    <input type="text" id="dispatchQuickCustDoc" class="form-control form-control-sm" placeholder="Ej. 1144...">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark mb-1">Teléfono:</label>
                    <input type="tel" id="dispatchQuickCustPhone" class="form-control form-control-sm" placeholder="Ej. 312...">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark mb-1">Dirección:</label>
                    <input type="text" id="dispatchQuickCustAddress" class="form-control form-control-sm" placeholder="Ej. Cra 105 # 45-20">
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light btn-sm border px-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success btn-sm px-3 fw-bold" style="background-color: #10B981; border-color: #10B981;" id="btnSubmitDispatchQuickCustomer">
                    Guardar y Asignar
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
