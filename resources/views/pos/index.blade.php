@extends('layouts.app')

@section('title', 'Punto de Venta POS | SaborCaliFit')

@section('content')
<style>
    .pos-product-grid {
        align-content: flex-start !important;
        align-items: flex-start !important;
        max-height: calc(100vh - 220px);
    }
    .product-tactile-card {
        height: 124px;
        min-height: 124px;
        max-height: 124px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        border-radius: 0.75rem;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 0.65rem 0.75rem;
        transition: all 0.15s ease-in-out;
        cursor: pointer;
        user-select: none;
    }
    .product-tactile-card:hover {
        border-color: #10B981 !important;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15) !important;
        transform: translateY(-2px);
    }
    .product-tactile-card:active {
        transform: scale(0.98);
        background-color: #f8fafc;
    }
    .product-title-clamp {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.85rem;
        font-weight: 600;
        color: #1e293b;
        line-height: 1.25;
        text-align: left;
        margin: 4px 0;
    }
    .product-badge-dish {
        background-color: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .product-badge-supplement {
        background-color: #ede9fe;
        color: #6d28d9;
        border: 1px solid #ddd6fe;
        font-size: 0.7rem;
        font-weight: 600;
    }

    /* Ergonomía táctil y diseño estético según ui-ux-design/SKILL.md e Imagen 1 */
    .btn-stepper-touch {
        min-width: 40px !important;
        min-height: 40px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        touch-action: manipulation;
    }
    .input-stepper-touch {
        min-height: 40px !important;
        text-align: center !important;
        font-weight: 700 !important;
        font-family: monospace, monospace !important;
        font-size: 1rem !important;
    }
    .input-stepper-touch::-webkit-outer-spin-button,
    .input-stepper-touch::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0 !important;
    }
    .input-stepper-touch[type=number] {
        -moz-appearance: textfield !important;
    }
    .pos-total-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        text-align: center;
        transition: all 0.2s ease;
    }
    .pos-total-box:hover {
        border-color: #cbd5e1;
    }
    .pos-customer-chip {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 0.5rem 0.75rem;
    }
    .quick-cash-pill {
        min-height: 38px;
        font-weight: 600;
        font-size: 0.85rem;
        border-radius: 0.5rem;
        padding: 0.35rem 0.75rem;
        touch-action: manipulation;
    }
    .pos-pay-line-card {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem;
        padding: 0.75rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
</style>

<div class="container-fluid px-2 px-md-3 py-2">
    <div class="row g-2">
        {{-- PANEL IZQUIERDO: CATÁLOGO HÍBRIDO TÁCTIL --}}
        <div class="col-lg-7 col-xl-8">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3">
                    {{-- Buscador y Filtros Rápidos --}}
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" id="catalogSearch" class="form-control border-start-0 ps-0" placeholder="Buscar plato o suplemento..." autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="btn-group w-100" role="group" aria-label="Filtro de productos">
                                <button type="button" class="btn btn-outline-success active catalog-filter-btn" data-category="all">
                                    <i class="bi bi-grid-fill me-1"></i> Todos
                                </button>
                                <button type="button" class="btn btn-outline-success catalog-filter-btn" data-category="platos">
                                    <i class="bi bi-egg-fried me-1"></i> Platos Fit
                                </button>
                                <button type="button" class="btn btn-outline-success catalog-filter-btn" data-category="suplementos">
                                    <i class="bi bi-capsule me-1"></i> Suplementos
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Cuadrícula de Productos --}}
                    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-2 overflow-auto pos-product-grid" id="productGrid">
                        @foreach($products as $p)
                            @php
                                $isDish = $p->isDish() || $p->tipo_producto === 'PLATO';
                                $catClass = $isDish ? 'platos' : 'suplementos';
                            @endphp
                            <div class="col product-card-col" data-category="{{ $catClass }}" data-name="{{ strtolower($p->nombre) }}" data-sku="{{ strtolower($p->sku) }}">
                                <div class="product-tactile-card shadow-sm"
                                     role="button"
                                     tabindex="0"
                                     onclick="handleProductClick({{ $p->id }}, {{ $isDish ? 'true' : 'false' }})">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge {{ $isDish ? 'product-badge-dish' : 'product-badge-supplement' }} rounded-pill px-2 py-0.5">
                                            <i class="bi {{ $isDish ? 'bi-egg-fried' : 'bi-capsule' }} me-1"></i>{{ $isDish ? 'Plato' : 'Suplemento' }}
                                        </span>
                                        <span class="badge {{ (float)$p->stock <= 0 ? 'bg-danger-subtle text-danger' : 'bg-light text-muted border' }} rounded-pill" style="font-size: 0.68rem;">
                                            Stock: {{ $p->formatted_stock }}
                                        </span>
                                    </div>
                                    <div class="product-title-clamp" title="{{ $p->nombre }}">
                                        {{ $p->nombre }}
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center pt-1 border-top border-light mt-auto">
                                        <span class="fw-bold fs-6" style="color: #10B981;">
                                            ${{ number_format($p->precio_venta, 0, ',', '.') }}
                                        </span>
                                        <span class="rounded-circle d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; background-color: #ecfdf5; color: #059669;">
                                            <i class="bi bi-plus-lg fw-bold" style="font-size: 0.75rem;"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- PANEL DERECHO: CARRITO Y LIQUIDACIÓN (ESTILO IMAGEN 1) --}}
        <div class="col-lg-5 col-xl-4">
            <div class="card border-0 shadow-sm rounded-3 d-flex flex-column" style="height: calc(100vh - 90px);">
                <div class="card-header bg-white border-bottom py-2 px-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark fs-6"><i class="bi bi-cart3 text-success me-1"></i> Comanda Mostrador</span>
                        <button type="button" class="btn btn-outline-danger btn-sm py-1 px-2.5 rounded-pill" onclick="clearCart()" title="Limpiar comanda">
                            <i class="bi bi-trash me-1"></i><span class="small fw-semibold">Vaciar</span>
                        </button>
                    </div>

                    {{-- SECCIÓN CLIENTE (ESTILO IMAGEN 1) --}}
                    <div class="mb-1">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold text-muted"><i class="bi bi-person me-1"></i>Cliente</span>
                            <span class="badge bg-light text-secondary border font-monospace fw-normal" style="font-size: 0.7rem;">Opcional</span>
                        </div>

                        {{-- Chip de Cliente Seleccionado --}}
                        <div class="pos-customer-chip d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center overflow-hidden me-2">
                                <div class="rounded-circle bg-white border d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 32px; height: 32px;">
                                    <i class="bi bi-person text-secondary" id="posCustomerIcon"></i>
                                </div>
                                <div class="small text-truncate">
                                    <span class="fw-bold d-block text-truncate text-dark" id="displayCustomerName" style="font-size: 0.85rem;">{{ $defaultCustomer['name'] }}</span>
                                    <span class="text-muted font-monospace small" id="displayCustomerDoc" style="font-size: 0.75rem;">CC/NIT: {{ $defaultCustomer['document'] }}</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 rounded-pill d-none" id="btnPosResetCustomer" onclick="resetToFinalConsumer()" title="Restablecer a Consumidor Final">
                                <i class="bi bi-x"></i> Quitar
                            </button>
                        </div>

                        {{-- Selector Triple Unificado de Cliente en Comanda Mostrador --}}
                        <div class="mb-2">
                            <div class="input-group input-group-sm mb-1">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" id="posCustomerSearch" class="form-control border-start-0 ps-0" placeholder="Buscar cliente por cédula, nombre..." autocomplete="off" oninput="filterPosLateralCustomerSelect(this.value)">
                            </div>
                            <div class="input-group input-group-sm position-relative">
                                <select id="posCustomerLateralSelect" class="form-select form-select-sm" onchange="onPosLateralCustomerSelect(this)">
                                    <option value="" data-name="{{ $defaultCustomer['name'] }}" data-doc="{{ $defaultCustomer['document'] }}" data-phone="">Consumidor Final (222222222222)</option>
                                    @foreach($customers as $c)
                                        @if($c->document !== '222222222222')
                                            <option value="{{ $c->id }}"
                                                    data-name="{{ $c->name }}"
                                                    data-doc="{{ $c->document ?: '222222222222' }}"
                                                    data-phone="{{ $c->phone ?? '' }}">
                                                {{ $c->name }} ({{ $c->document ?: 'Sin doc' }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-success px-2.5" data-bs-toggle="modal" data-bs-target="#customerModal" title="Crear Cliente Rápido">
                                    <i class="bi bi-person-plus-fill"></i>
                                </button>
                            </div>
                            {{-- Dropdown de resultados predictivos --}}
                            <div id="posCustomerResults" class="list-group position-absolute shadow-lg w-100 mt-1 d-none" style="z-index: 1060; max-height: 220px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>

                {{-- Contenedor de Ítems del Carrito --}}
                <div class="card-body p-2 flex-grow-1 overflow-auto" id="cartContainer">
                    <div id="cartEmptyState" class="text-center text-muted py-5">
                        <i class="bi bi-basket3 display-4 text-secondary opacity-50"></i>
                        <p class="mt-2 mb-0 small">No hay ítems en la comanda.</p>
                        <small class="text-muted">Selecciona platos o suplementos del catálogo.</small>
                    </div>
                    <ul class="list-group list-group-flush" id="cartItemsList"></ul>
                </div>

                {{-- RESUMEN DE COBRO Y ACCIONES (ESTILO IMAGEN 1) --}}
                <div class="card-footer bg-white border-top p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold"><i class="bi bi-calculator me-1"></i>Subtotal</span>
                        <span class="fw-bold text-dark font-monospace" id="summarySubtotal">$0</span>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted fw-semibold">Descuento (%)</span>
                            <span class="text-danger fw-bold font-monospace small" id="summaryDiscount">-$0</span>
                        </div>
                        <div class="input-group">
                            <button type="button" class="btn btn-outline-secondary px-3 btn-stepper-touch" id="btnPosDiscountMinus" title="Disminuir descuento" onclick="stepDiscount(-1)">
                                <i class="bi bi-dash-lg"></i>
                            </button>
                            <input type="number" id="discountPercent" class="form-control text-center input-stepper-touch"
                                   value="0" min="0" max="100" step="1" placeholder="0" oninput="onDiscountInput(this.value)">
                            <button type="button" class="btn btn-outline-secondary px-3 btn-stepper-touch" id="btnPosDiscountPlus" title="Aumentar descuento" onclick="stepDiscount(1)">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                            <span class="input-group-text bg-light fw-bold text-muted">%</span>
                        </div>
                    </div>

                    {{-- Caja destacada de Total a Cobrar (Idéntica a Imagen 1) --}}
                    <div class="pos-total-box my-2">
                        <span class="text-muted small d-block text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL A COBRAR</span>
                        <span class="fw-black fs-2 font-monospace d-block" id="summaryTotal" style="color: #10B981;">$0</span>
                    </div>

                    <button type="button" class="btn btn-califit w-100 py-3 fs-6 rounded-pill shadow-sm d-flex justify-content-center align-items-center gap-2 fw-bold" style="min-height: 48px;" id="btnOpenPaymentModal" onclick="openPaymentModal()">
                        <i class="bi bi-wallet2 fs-5"></i>
                        <span>COBRAR / LIQUIDAR</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL RÁPIDO DE ADICIONES PARA PLATOS --}}
<div class="modal fade" id="additionsModal" tabindex="-1" aria-labelledby="additionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold" id="additionsModalLabel">Adiciones para el Plato</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="fw-bold fs-6 text-dark" id="modalDishName">-</span>
                    <span class="badge bg-success-subtle text-success fs-6" id="modalDishPrice">$0</span>
                </div>
                <div class="text-muted small mb-3">Selecciona las adiciones deseadas con sus porciones:</div>

                <div id="modalAdditionsList" class="vstack gap-2" style="max-height: 280px; overflow-y: auto;">
                    {{-- Generado dinámicamente según las adiciones del plato --}}
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-califit btn-sm px-3" onclick="confirmDishWithAdditions()">
                    <i class="bi bi-check-circle me-1"></i> Agregar a Comanda
                </button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DE CLIENTES CON BÚSQUEDA Y LISTA COMPACTA --}}
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light py-2">
                <h6 class="modal-title fw-bold text-dark">
                    <i class="bi bi-people-fill text-success me-1"></i> Seleccionar o Asignar Cliente
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <button type="button" class="btn btn-outline-success w-100 mb-3 text-start py-2 fw-semibold" onclick="selectCustomer(null, 'CONSUMIDOR FINAL', '222222222222')">
                    <i class="bi bi-person-check-fill me-2 fs-5"></i> Consumidor Final <span class="fw-normal text-muted small">(222222222222)</span>
                </button>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-muted mb-0">Clientes Frecuentes / Registrados:</label>
                        <span class="badge bg-light text-muted border small">{{ count($customers) }} registrados</span>
                    </div>

                    {{-- Buscador dinámico por texto --}}
                    <div class="input-group input-group-sm mb-2 shadow-none">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="customerSearchInput" class="form-control border-start-0 ps-0" placeholder="Buscar por nombre, cédula o teléfono..." oninput="filterCustomerSelect(this.value)">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearCustomerSearch()" title="Limpiar búsqueda">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>

                    {{-- Lista desplegable de clientes para selección directa --}}
                    <div>
                        <select id="customerDropdownSelect" class="form-select form-select-sm" onchange="onCustomerDropdownSelect(this)">
                            <option value="">-- Seleccionar cliente de la lista desplegable --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}"
                                        data-name="{{ $c->name }}"
                                        data-doc="{{ $c->document ?? '222222222222' }}"
                                        data-search="{{ strtolower($c->name . ' ' . ($c->document ?? '') . ' ' . ($c->phone ?? '')) }}">
                                    {{ $c->name }} {{ $c->document ? '('.$c->document.')' : '' }} {{ $c->phone ? '| Tel: '.$c->phone : '' }}
                                </option>
                            @endforeach
                        </select>
                        <div id="customerSearchFeedback" class="small text-muted mt-1 d-none" style="font-size: 0.75rem;"></div>
                    </div>
                </div>

                <div class="border-top pt-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Cliente Ocasional Rápido:</label>
                    <div class="row g-2">
                        <div class="col-7">
                            <input type="text" id="customClientName" class="form-control form-control-sm" placeholder="Nombre completo *" oninput="this.classList.remove('is-invalid')">
                            <div class="invalid-feedback small">Por favor digita al menos el nombre del cliente.</div>
                        </div>
                        <div class="col-5">
                            <input type="text" id="customClientDoc" class="form-control form-control-sm" placeholder="Cédula / NIT">
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-dark btn-sm w-100 mt-2" onclick="saveCustomCustomer()">
                        <i class="bi bi-person-plus-fill me-1"></i> Asignar Cliente Ocasional
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DE CIERRE DE VENTA Y COBRO (DISEÑO Y ERGONOMÍA IMAGEN 1) --}}
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            {{-- Cabecera Estilizada Limpia --}}
            <div class="modal-header d-flex align-items-start justify-content-between pb-3 border-bottom bg-white py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-3 p-2 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title fw-bold text-dark mb-0">Datos de la Venta</h5>
                            <span class="badge bg-light text-secondary border fw-normal" id="payModalDateBadge" style="font-size: 0.75rem;">
                                <i class="bi bi-calendar3 me-1"></i>{{ now()->format('d/m/Y') }}
                            </span>
                        </div>
                        <p class="text-muted small mb-0 mt-1">Registro de pago y finalización de comanda</p>
                    </div>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4 bg-white">
                <div class="row g-4">
                    {{-- COLUMNA IZQUIERDA: CONTEXTO Y MÉTODOS DE PAGO --}}
                    <div class="col-lg-7">
                        {{-- 1. Fecha y Hora --}}
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted d-flex justify-content-between align-items-center mb-1">
                                <span><i class="bi bi-clock-history me-1"></i>Fecha y Hora</span>
                                <span class="badge bg-light text-secondary border font-monospace fw-normal">{{ now()->format('d/m/Y') }}</span>
                            </label>
                            <input type="datetime-local" id="payModalDate" class="form-control form-control-sm font-monospace"
                                   value="{{ now()->format('Y-m-d\TH:i') }}" readonly>
                        </div>

                        {{-- 2. Selector Triple Unificado de Cliente --}}
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted d-flex justify-content-between align-items-center mb-1">
                                <span><i class="bi bi-person text-success me-1"></i>Cliente / Comprador</span>
                                <span class="badge bg-light text-secondary border font-monospace fw-normal" style="font-size: 0.7rem;">Opcional</span>
                            </label>
                            <div class="input-group input-group-sm mb-1">
                                <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" id="payModalCustomerSearch" class="form-control" placeholder="Buscar por nombre, cédula o teléfono..." oninput="filterPayModalCustomerSelect(this.value)" autocomplete="off">
                            </div>
                            <div class="input-group input-group-sm">
                                <select id="payModalCustomerSelect" class="form-select form-select-sm" onchange="onPayModalCustomerSelectChange(this)">
                                    <option value="" data-name="{{ $defaultCustomer['name'] }}" data-doc="{{ $defaultCustomer['document'] }}" data-phone="">Consumidor Final (222222222222)</option>
                                    @foreach($customers as $c)
                                        @if($c->document !== '222222222222')
                                            <option value="{{ $c->id }}"
                                                    data-name="{{ $c->name }}"
                                                    data-doc="{{ $c->document ?: '222222222222' }}"
                                                    data-phone="{{ $c->phone ?? '' }}">
                                                {{ $c->name }} ({{ $c->document ?: 'Sin doc' }}) {{ $c->phone ? '| Tel: ' . $c->phone : '' }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#customerModal" title="Crear Cliente Rápido">
                                    <i class="bi bi-person-plus-fill"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Opción Venta a Crédito / Fiado --}}
                        <div class="card p-3 border rounded-3 mb-3 bg-light">
                            <div class="form-check form-switch d-flex align-items-center justify-content-between ps-0 mb-0">
                                <label class="form-check-label fw-bold text-dark cursor-pointer mb-0" for="posCreditSwitch">
                                    <i class="bi bi-wallet2 text-danger me-1"></i> Despachar a Crédito / Fiado (Cuenta por Cobrar)
                                </label>
                                <input class="form-check-input ms-2" type="checkbox" id="posCreditSwitch" onchange="togglePosCreditMode(this.checked)" autocomplete="off" style="width: 2.5em; height: 1.3em;">
                            </div>
                            <div id="posCreditFields" class="mt-3 pt-3 border-top d-none">
                                <div class="alert alert-warning py-2 px-3 rounded-3 small mb-2 d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill text-warning me-2 fs-5"></i>
                                    <div>
                                        <strong>Aislamiento de Caja:</strong> El inventario se descontará inmediatamente, pero no sumará dinero en gaveta ni transferencias hoy.
                                    </div>
                                </div>
                                <div id="posCreditCustomerAlert" class="alert alert-danger py-2 px-3 rounded-3 small mb-2 d-none">
                                    <i class="bi bi-x-circle me-1 fw-bold"></i> Para otorgar crédito debe seleccionar un cliente registrado con teléfono. No permitido para Consumidor Final.
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-dark mb-1">Fecha Límite de Pago:</label>
                                        <input type="date" id="posCreditDueDate" class="form-control form-control-sm" value="{{ now()->addDays(15)->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-dark mb-1">Estado de Cartera:</label>
                                        <input type="text" class="form-control form-control-sm bg-white" readonly value="PENDIENTE DE COBRO">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 3. Métodos de Pago --}}
                        <div class="border-top pt-3" id="posStandardPaymentSection">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-semibold text-muted mb-0">
                                    <i class="bi bi-wallet2 me-1"></i>Métodos de Pago
                                </label>
                                <button type="button" class="btn btn-sm btn-outline-success rounded-pill py-1 px-3 fw-semibold" onclick="addPaymentLine()" id="btnAddPaymentLine">
                                    <i class="bi bi-plus-circle me-1"></i>Agregar método
                                </button>
                            </div>

                            {{-- Encabezados de columnas --}}
                            <div class="row g-2 text-muted fw-bold px-2 mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                <div class="col-4 text-uppercase">1. Método</div>
                                <div class="col-4 text-uppercase">2. Monto a Cobrar</div>
                                <div class="col-3 text-uppercase">3. Recibido / Soporte</div>
                                <div class="col-1 text-center"></div>
                            </div>

                            <div id="paymentLinesContainer" class="vstack gap-2 mb-2"></div>

                            {{-- Indicador dinámico de balance / saldo --}}
                            <div class="p-2 bg-light rounded-3 border text-center small mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Total cubierto: <strong id="payModalCovered" class="text-dark font-monospace">$0</strong></span>
                                    <div id="payModalDiff"><span class="fw-bold text-success font-monospace">$0 (Exacto)</span></div>
                                </div>
                            </div>

                            {{-- Atajos rápidos de billetes para efectivo --}}
                            <div class="pt-2 border-top">
                                <span class="small text-muted d-block mb-1.5 fw-semibold"><i class="bi bi-lightning-charge me-1"></i>Atajos Rápidos de Efectivo:</span>
                                <div class="d-flex flex-wrap gap-1.5">
                                    <button type="button" class="btn btn-sm btn-outline-secondary quick-cash-pill" onclick="setCashQuick('exact')">Exacto</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary quick-cash-pill" onclick="setCashQuick(10000)">$10.000</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary quick-cash-pill" onclick="setCashQuick(20000)">$20.000</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary quick-cash-pill" onclick="setCashQuick(50000)">$50.000</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary quick-cash-pill" onclick="setCashQuick(100000)">$100.000</button>
                                </div>
                            </div>

                            {{-- Caja Destacada de Cambio / Vuelto al Cliente --}}
                            <div class="p-3 rounded-3 mt-3" id="changeIndicatorBox" style="background-color: #ecfdf5; border: 1px solid #a7f3d0; transition: all 0.2s ease;">
                                <div id="changeDetailsBox">
                                    <div class="small fw-bold text-muted mb-1 text-uppercase" id="changeLabel">Cambio / Vuelto al Cliente</div>
                                    <h4 class="fw-black mb-0 text-success font-monospace" id="changeAmount">$0</h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- COLUMNA DERECHA: RESUMEN DE COBRO, NOTAS Y REGISTRAR VENTA --}}
                    <div class="col-lg-5">
                        <div class="card p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                {{-- Resumen de Cobro --}}
                                <div class="border-bottom pb-3 mb-3">
                                    <h6 class="fw-bold text-uppercase text-muted small mb-2"><i class="bi bi-calculator me-1"></i>Resumen de Cobro</h6>

                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">Subtotal</span>
                                        <span class="fw-semibold font-monospace" id="payModalSubtotalDisplay">$0</span>
                                    </div>

                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="form-label small text-muted mb-0">Descuento (%)</span>
                                            <span class="fw-semibold text-danger font-monospace small" id="payModalDiscountDisplay">-$0</span>
                                        </div>
                                        <div class="input-group">
                                            <button type="button" class="btn btn-outline-secondary px-3 btn-stepper-touch" id="btnModalDiscountMinus" title="Disminuir descuento" onclick="stepDiscount(-1)">
                                                <i class="bi bi-dash-lg"></i>
                                            </button>
                                            <input type="number" id="payModalDiscountPercent" class="form-control text-center input-stepper-touch"
                                                   value="0" min="0" max="100" step="1" placeholder="0" oninput="onDiscountInput(this.value)">
                                            <button type="button" class="btn btn-outline-secondary px-3 btn-stepper-touch" id="btnModalDiscountPlus" title="Aumentar descuento" onclick="stepDiscount(1)">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                            <span class="input-group-text bg-white fw-bold text-muted">%</span>
                                        </div>
                                    </div>

                                    {{-- Caja Destacada de Total a Cobrar (Idéntica a Imagen 1) --}}
                                    <div class="bg-white p-3 rounded-3 border mt-3 text-center shadow-sm">
                                        <span class="text-muted small d-block text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL A COBRAR</span>
                                        <span class="fw-black fs-2 font-monospace d-block" id="payModalTotal" style="color: #10B981;">$0</span>
                                    </div>
                                </div>

                                {{-- Notas u observaciones --}}
                                <div class="mb-3">
                                    <label for="posSaleNotes" class="form-label small fw-semibold text-muted mb-1">
                                        <i class="bi bi-chat-left-text me-1"></i>Notas
                                    </label>
                                    <textarea id="posSaleNotes" class="form-control form-control-sm rounded-3" rows="3" maxlength="500"
                                              placeholder="Notas u observaciones adicionales..."></textarea>
                                </div>
                            </div>

                            {{-- Botón de Acción Principal (Idéntico a Imagen 1) --}}
                            <div>
                                <button type="button" class="btn btn-califit w-100 py-3 rounded-pill fw-bold shadow-sm d-flex justify-content-center align-items-center gap-2" style="min-height: 50px;" id="btnSubmitSale" onclick="submitSale()">
                                    <i class="bi bi-check2-circle fs-5"></i>
                                    <span class="fs-6">Registrar Venta</span>
                                </button>
                                <small class="text-muted text-center d-block mt-2" style="font-size: 0.72rem;">
                                    <i class="bi bi-printer me-1"></i>Genera e imprime el ticket de 58 mm automáticamente.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ESTADO GLOBAL DE POS REACTIVO
const ALL_PRODUCTS = @json($products);
const ALL_ADDITIONS = @json($adiciones);
let cart = [];
let selectedCustomer = {
    id: null,
    name: "{{ $defaultCustomer['name'] }}",
    document: "{{ $defaultCustomer['document'] }}"
};

let currentDishSelection = null;
let paymentLines = [];

document.addEventListener('DOMContentLoaded', () => {
    // Filtro de búsqueda en tiempo real
    const catSearch = document.getElementById('catalogSearch');
    if (catSearch) catSearch.addEventListener('input', filterCatalog);

    // Botones de pestañas de categorías
    document.querySelectorAll('.catalog-filter-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.catalog-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterCatalog();
        });
    });

    initPosCustomerSearch();
    renderCart();
});

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function initPosCustomerSearch() {
    const input = document.getElementById('posCustomerSearch');
    const resultsBox = document.getElementById('posCustomerResults');
    if (!input || !resultsBox) return;

    let searchTimer = null;
    let abortController = null;

    input.addEventListener('input', function () {
        clearTimeout(searchTimer);
        const query = this.value.trim();

        if (query.length < 2) {
            resultsBox.classList.add('d-none');
            resultsBox.innerHTML = '';
            return;
        }

        searchTimer = setTimeout(async () => {
            if (abortController) abortController.abort();
            abortController = new AbortController();

            try {
                const res = await fetch(`{{ route('customers.search') }}?q=${encodeURIComponent(query)}`, {
                    signal: abortController.signal,
                    headers: { 'Accept': 'application/json' }
                });
                const customers = await res.json();
                renderPosCustomerResults(customers, query);
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error('Error buscando clientes en POS:', err);
                }
            }
        }, 180);
    });

    // Ocultar resultados al hacer clic fuera del componente
    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !resultsBox.contains(e.target)) {
            resultsBox.classList.add('d-none');
        }
    });
}

function renderPosCustomerResults(customers, query) {
    const resultsBox = document.getElementById('posCustomerResults');
    if (!resultsBox) return;

    resultsBox.innerHTML = '';

    if (!Array.isArray(customers) || customers.length === 0) {
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'list-group-item text-muted small py-2 d-flex justify-content-between align-items-center bg-white';

        const span = document.createElement('span');
        span.innerHTML = `<i class="bi bi-info-circle me-1"></i>Sin coincidencias para "${escapeHtml(query)}"`;

        const createBtn = document.createElement('button');
        createBtn.type = 'button';
        createBtn.className = 'btn btn-xs btn-outline-success py-0 px-2 fw-semibold';
        createBtn.innerHTML = '<i class="bi bi-plus-lg me-1"></i>Crear';
        createBtn.addEventListener('click', () => openQuickCreateCustomerFromSearch(query));

        emptyDiv.appendChild(span);
        emptyDiv.appendChild(createBtn);
        resultsBox.appendChild(emptyDiv);
        resultsBox.classList.remove('d-none');
        return;
    }

    customers.forEach(c => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 text-start bg-white';
        item.innerHTML = `
            <div class="overflow-hidden me-2">
                <span class="fw-bold d-block text-truncate text-dark small">${escapeHtml(c.name)}</span>
                <span class="text-muted font-monospace" style="font-size: 0.72rem;">CC/NIT: ${escapeHtml(c.document || '222222222222')}</span>
                ${c.phone ? `<span class="text-secondary ms-2" style="font-size: 0.72rem;"><i class="bi bi-telephone me-1"></i>${escapeHtml(c.phone)}</span>` : ''}
            </div>
            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 flex-shrink-0" style="font-size: 0.7rem;">
                <i class="bi bi-check2 me-1"></i>Elegir
            </span>
        `;
        item.addEventListener('click', () => {
            selectCustomer(c.id, c.name, c.document);
            const searchInput = document.getElementById('posCustomerSearch');
            if (searchInput) searchInput.value = '';
            resultsBox.classList.add('d-none');
        });
        resultsBox.appendChild(item);
    });

    resultsBox.classList.remove('d-none');
}

function openQuickCreateCustomerFromSearch(initialQuery) {
    const resultsBox = document.getElementById('posCustomerResults');
    if (resultsBox) resultsBox.classList.add('d-none');

    const isNumeric = /^\d+$/.test(initialQuery.trim());
    if (isNumeric) {
        document.getElementById('customClientDoc').value = initialQuery.trim();
        document.getElementById('customClientName').value = '';
    } else {
        document.getElementById('customClientName').value = initialQuery.trim();
        document.getElementById('customClientDoc').value = '';
    }

    const modal = new bootstrap.Modal(document.getElementById('customerModal'));
    modal.show();
}

// Filtrar catálogo por texto y categoría
function filterCatalog() {
    const term = document.getElementById('catalogSearch').value.toLowerCase().trim();
    const activeCategory = document.querySelector('.catalog-filter-btn.active').dataset.category;

    document.querySelectorAll('.product-card-col').forEach(card => {
        const name = card.dataset.name;
        const sku = card.dataset.sku;
        const cat = card.dataset.category;

        const matchesText = name.includes(term) || sku.includes(term);
        const matchesCat = activeCategory === 'all' || cat === activeCategory;

        if (matchesText && matchesCat) {
            card.classList.remove('d-none');
        } else {
            card.classList.add('d-none');
        }
    });
}

// Clic en producto del catálogo
function handleProductClick(productId, isDish) {
    const product = ALL_PRODUCTS.find(p => p.id === productId);
    if (!product) return;

    if (!isDish) {
        // Suplemento: agregar directamente con cantidad 1
        addToCart(product, []);
    } else {
        // Plato: abrir modal de adiciones
        openAdditionsModal(product);
    }
}

// Abrir modal de adiciones para un plato
function openAdditionsModal(dish) {
    currentDishSelection = dish;
    document.getElementById('modalDishName').textContent = dish.nombre;
    document.getElementById('modalDishPrice').textContent = '$' + Number(dish.precio_venta).toLocaleString('es-CO');

    const container = document.getElementById('modalAdditionsList');
    container.innerHTML = '';

    // Filtrar adiciones permitidas para este plato si existen en la relación, o mostrar todas las activas
    const availableAdditions = (dish.adiciones && dish.adiciones.length > 0) ? dish.adiciones : ALL_ADDITIONS;

    if (availableAdditions.length === 0) {
        container.innerHTML = '<div class="text-muted small text-center py-2">No hay adiciones configuradas para este plato.</div>';
    } else {
        availableAdditions.forEach(add => {
            const addName = add.nombre || add.name || '';
            const addPrice = parseFloat(add.precio || add.unit_price || 0);
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center p-2 rounded border bg-white';
            row.innerHTML = `
                <div>
                    <div class="fw-semibold small text-dark">${escapeHtml(addName)}</div>
                    <div class="text-success small fw-bold">+ $${Number(addPrice).toLocaleString('es-CO')}</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" onclick="adjustModalAddQty(${add.id}, -1)">-</button>
                    <span class="fw-bold small modal-add-qty" id="modalAddQty_${add.id}" data-id="${add.id}" data-price="${addPrice}" data-name="${escapeHtml(addName)}">0</span>
                    <button type="button" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" onclick="adjustModalAddQty(${add.id}, 1)">+</button>
                </div>
            `;
            container.appendChild(row);
        });
    }

    const modal = new bootstrap.Modal(document.getElementById('additionsModal'));
    modal.show();
}

function adjustModalAddQty(addId, delta) {
    const el = document.getElementById(`modalAddQty_${addId}`);
    if (!el) return;
    let qty = parseInt(el.textContent) || 0;
    qty = Math.max(0, qty + delta);
    el.textContent = qty;
}

function confirmDishWithAdditions() {
    if (!currentDishSelection) return;

    const selectedAdds = [];
    document.querySelectorAll('.modal-add-qty').forEach(el => {
        const qty = parseInt(el.textContent) || 0;
        if (qty > 0) {
            selectedAdds.push({
                addition_id: parseInt(el.dataset.id),
                name: el.dataset.name,
                unit_price: parseFloat(el.dataset.price),
                qty_per_dish: qty,
                quantity: qty
            });
        }
    });

    addToCart(currentDishSelection, selectedAdds);

    const modalEl = document.getElementById('additionsModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();
    currentDishSelection = null;
}

// Agregar al carrito
function addToCart(product, additions = []) {
    // Normalizar adiciones asegurando qty_per_dish
    const normalizedAdds = additions.map(add => {
        const qpd = add.qty_per_dish || add.quantity || 1;
        return {
            addition_id: parseInt(add.addition_id || add.id),
            name: add.name || add.nombre,
            unit_price: parseFloat(add.unit_price || add.precio || 0),
            qty_per_dish: qpd,
            quantity: qpd
        };
    });

    // Buscar si ya existe la misma combinación producto + adiciones por plato
    const additionsKey = JSON.stringify(normalizedAdds.map(a => `${a.addition_id}:${a.qty_per_dish}`).sort());
    const existing = cart.find(item => item.product_id === product.id && item.additionsKey === additionsKey);

    if (existing) {
        existing.quantity += 1;
        if (existing.additions && existing.additions.length > 0) {
            existing.additions.forEach(add => {
                const qpd = add.qty_per_dish || 1;
                add.quantity = qpd * existing.quantity;
            });
        }
    } else {
        const itemQuantity = 1;
        const finalAdditions = normalizedAdds.map(add => ({
            ...add,
            quantity: add.qty_per_dish * itemQuantity
        }));
        cart.push({
            product_id: product.id,
            nombre: product.nombre || product.name,
            unit_price: parseFloat(product.precio_venta || product.unit_price || 0),
            quantity: itemQuantity,
            additions: finalAdditions,
            additionsKey: additionsKey
        });
    }

    renderCart();
}

function updateCartItemQty(index, delta) {
    if (!cart[index]) return;
    cart[index].quantity += delta;
    if (cart[index].quantity <= 0) {
        cart.splice(index, 1);
    } else {
        if (cart[index].additions && cart[index].additions.length > 0) {
            cart[index].additions.forEach(add => {
                const qpd = add.qty_per_dish || 1;
                add.quantity = qpd * cart[index].quantity;
            });
        }
    }
    renderCart();
}

function removeCartItem(index) {
    cart.splice(index, 1);
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;
    window.califitConfirm({
        title: '¿Vaciar Comanda Actual?',
        message: 'Se eliminarán todos los productos y adiciones cargados en la comanda de mostrador.',
        confirmText: 'Sí, Vaciar Comanda',
        confirmClass: 'btn-danger',
        icon: 'bi-trash',
        onConfirm: function() {
            cart = [];
            renderCart();
            if (typeof califitNotify === 'function') {
                califitNotify('Comanda vaciada exitosamente.', 'info', 'Comanda Mostrador');
            }
        }
    });
}

// Renderizar carrito visual
function renderCart() {
    const container = document.getElementById('cartItemsList');
    const emptyState = document.getElementById('cartEmptyState');
    container.innerHTML = '';

    if (cart.length === 0) {
        emptyState.classList.remove('d-none');
    } else {
        emptyState.classList.add('d-none');

        cart.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'list-group-item px-2 py-2 border-bottom';

            let addsHtml = '';
            let additionsTotal = 0;
            if (item.additions && item.additions.length > 0) {
                addsHtml = '<div class="ms-2 my-1 border-start ps-2" style="font-size: 0.76rem; border-color: #10B981 !important;">';
                item.additions.forEach(add => {
                    const sub = add.unit_price * add.quantity;
                    additionsTotal += sub;
                    const perDishText = (item.quantity > 1 && add.qty_per_dish) ? ` <span class="text-muted fst-italic">(${add.qty_per_dish} por plato)</span>` : '';
                    addsHtml += `<div class="text-muted d-flex justify-content-between">
                        <span>+ ${escapeHtml(add.name || add.nombre)} (x${add.quantity})${perDishText}</span>
                        <span class="text-success fw-bold">+$${sub.toLocaleString('es-CO')}</span>
                    </div>`;
                });
                addsHtml += '</div>';
            }

            const lineTotal = (item.unit_price * item.quantity) + additionsTotal;

            li.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="fw-bold text-dark small">${escapeHtml(item.nombre || item.name)}</div>
                        <div class="text-muted small">$${item.unit_price.toLocaleString('es-CO')} c/u</div>
                        ${addsHtml}
                    </div>
                    <div class="text-end ms-2">
                        <div class="fw-bold text-dark small">$${lineTotal.toLocaleString('es-CO')}</div>
                        <div class="d-flex align-items-center gap-1 mt-1 justify-content-end">
                            <button type="button" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" onclick="updateCartItemQty(${index}, -1)">-</button>
                            <span class="small fw-bold px-1">${item.quantity}</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" onclick="updateCartItemQty(${index}, 1)">+</button>
                            <button type="button" class="btn btn-link text-danger p-0 ms-1" onclick="removeCartItem(${index})"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(li);
        });
    }

    calculateTotals();
}

// Cálculo financiero del carrito y sincronización de paneles
function calculateTotals() {
    let subtotal = 0;
    cart.forEach(item => {
        let line = item.unit_price * item.quantity;
        if (item.additions) {
            item.additions.forEach(add => line += add.unit_price * add.quantity);
        }
        subtotal += line;
    });

    const sidebarInp = document.getElementById('discountPercent');
    const modalInp = document.getElementById('payModalDiscountPercent');
    const discPct = Math.max(0, Math.min(100, parseFloat(sidebarInp ? sidebarInp.value : (modalInp ? modalInp.value : 0)) || 0));
    const discountVal = (subtotal * discPct) / 100;
    const delivery = 0;

    const total = Math.max(0, subtotal - discountVal);

    // Actualizar elementos del panel lateral
    const sumSub = document.getElementById('summarySubtotal');
    if (sumSub) sumSub.textContent = '$' + subtotal.toLocaleString('es-CO');
    const sumDisc = document.getElementById('summaryDiscount');
    if (sumDisc) sumDisc.textContent = '-$' + discountVal.toLocaleString('es-CO');
    const sumTot = document.getElementById('summaryTotal');
    if (sumTot) sumTot.textContent = '$' + total.toLocaleString('es-CO');

    // Actualizar elementos del modal de cierre/cobro
    const paySub = document.getElementById('payModalSubtotalDisplay');
    if (paySub) paySub.textContent = '$' + subtotal.toLocaleString('es-CO');
    const payDisc = document.getElementById('payModalDiscountDisplay');
    if (payDisc) payDisc.textContent = '-$' + discountVal.toLocaleString('es-CO');
    const payTot = document.getElementById('payModalTotal');
    if (payTot) payTot.textContent = '$' + total.toLocaleString('es-CO');

    return { subtotal, discountVal, discPct, delivery, total };
}

// Stepper interactivo para ajustar descuento con flechas táctiles (mín 40x40px)
function stepDiscount(delta) {
    const sidebarInp = document.getElementById('discountPercent');
    const modalInp = document.getElementById('payModalDiscountPercent');
    let currentVal = parseInt((sidebarInp && sidebarInp.value !== '') ? sidebarInp.value : (modalInp ? modalInp.value : 0), 10) || 0;
    let newVal = Math.max(0, Math.min(100, currentVal + delta));

    if (sidebarInp) sidebarInp.value = newVal;
    if (modalInp) modalInp.value = newVal;

    calculateTotals();

    if (paymentLines.length > 0) {
        if (paymentLines.length === 1 && paymentLines[0].method === 'cash') {
            const { total } = calculateTotals();
            paymentLines[0].amount = total;
            paymentLines[0].cash_received = total;
            renderPaymentLines();
        } else {
            recalculatePaymentSummary();
        }
    }
}

function onDiscountInput(val) {
    let num = parseInt(val, 10);
    if (isNaN(num) || num < 0) num = 0;
    if (num > 100) num = 100;

    const sidebarInp = document.getElementById('discountPercent');
    const modalInp = document.getElementById('payModalDiscountPercent');
    if (sidebarInp) sidebarInp.value = num;
    if (modalInp) modalInp.value = num;

    calculateTotals();

    if (paymentLines.length > 0) {
        if (paymentLines.length === 1 && paymentLines[0].method === 'cash') {
            const { total } = calculateTotals();
            paymentLines[0].amount = total;
            paymentLines[0].cash_received = total;
            renderPaymentLines();
        } else {
            recalculatePaymentSummary();
        }
    }
}

function adjustDiscount(delta) {
    stepDiscount(delta);
}

// Filtro en tiempo real para opciones de la lista desplegable de clientes
function filterCustomerSelect(query) {
    const term = (query || '').toLowerCase().trim();
    const select = document.getElementById('customerDropdownSelect');
    if (!select) return;

    let matchCount = 0;
    let firstMatch = null;

    Array.from(select.options).forEach((opt, idx) => {
        if (idx === 0) return; // Encabezado por defecto
        const searchData = opt.getAttribute('data-search') || opt.text.toLowerCase();
        if (!term || searchData.includes(term)) {
            opt.hidden = false;
            matchCount++;
            if (!firstMatch) firstMatch = opt;
        } else {
            opt.hidden = true;
        }
    });

    const feedback = document.getElementById('customerSearchFeedback');
    if (feedback) {
        if (term) {
            feedback.classList.remove('d-none');
            if (matchCount > 0) {
                feedback.className = 'small text-success mt-1';
                feedback.innerHTML = `<i class="bi bi-check-circle me-1"></i>${matchCount} cliente(s) coincidente(s) en la lista desplegable.`;
                if (matchCount === 1 && firstMatch) {
                    select.value = firstMatch.value;
                }
            } else {
                feedback.className = 'small text-danger mt-1';
                feedback.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>No se encontraron clientes registrados con ese criterio.`;
                select.value = '';
            }
        } else {
            feedback.classList.add('d-none');
            select.value = '';
        }
    }
}

function clearCustomerSearch() {
    const input = document.getElementById('customerSearchInput');
    if (input) {
        input.value = '';
        filterCustomerSelect('');
        input.focus();
    }
}

function onCustomerDropdownSelect(selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (selectEl.value && opt) {
        const id = parseInt(selectEl.value, 10);
        const name = opt.getAttribute('data-name');
        const doc = opt.getAttribute('data-doc');
        selectCustomer(id, name, doc);
        selectEl.value = '';
    }
}

// Selector centralizado de clientes
function selectCustomer(id, name, doc, phone = '') {
    const isFinalConsumer = (!id && (!doc || doc === '222222222222') && (!name || name === 'CONSUMIDOR FINAL'));
    selectedCustomer = {
        id: id || null,
        name: name || 'CONSUMIDOR FINAL',
        document: doc || '222222222222',
        phone: phone || ''
    };

    // Actualizar chip en comanda lateral
    const nameEl = document.getElementById('displayCustomerName');
    if (nameEl) nameEl.textContent = selectedCustomer.name;
    const docEl = document.getElementById('displayCustomerDoc');
    if (docEl) docEl.textContent = 'CC/NIT: ' + selectedCustomer.document;

    const iconEl = document.getElementById('posCustomerIcon');
    if (iconEl) {
        iconEl.className = isFinalConsumer ? 'bi bi-person text-secondary' : 'bi bi-person-check-fill text-success';
    }

    const resetBtn = document.getElementById('btnPosResetCustomer');
    if (resetBtn) {
        if (isFinalConsumer) {
            resetBtn.classList.add('d-none');
        } else {
            resetBtn.classList.remove('d-none');
        }
    }

    // Sincronizar select en modal de cobro
    const payCustSel = document.getElementById('payModalCustomerSelect');
    if (payCustSel) {
        if (id) {
            let optExists = Array.from(payCustSel.options).some(o => o.value == id);
            if (!optExists) {
                const newOpt = new Option(`${name} (${doc || 'Sin doc'})${phone ? ' | Tel: ' + phone : ''}`, id, true, true);
                newOpt.dataset.name = name;
                newOpt.dataset.doc = doc || '222222222222';
                newOpt.dataset.phone = phone || '';
                payCustSel.add(newOpt);
            }
            payCustSel.value = id;
        } else {
            payCustSel.value = '';
        }
    }

    // Sincronizar select en comanda lateral
    const lateralSel = document.getElementById('posCustomerLateralSelect');
    if (lateralSel) {
        if (id) {
            let optExists = Array.from(lateralSel.options).some(o => o.value == id);
            if (!optExists) {
                const newOpt = new Option(`${name} (${doc || 'Sin doc'})`, id, true, true);
                newOpt.dataset.name = name;
                newOpt.dataset.doc = doc || '222222222222';
                newOpt.dataset.phone = phone || '';
                lateralSel.add(newOpt);
            }
            lateralSel.value = id;
        } else {
            lateralSel.value = '';
        }
    }

    // Si crédito está activo, verificar validación
    const creditSw = document.getElementById('posCreditSwitch');
    if (creditSw && creditSw.checked) {
        const alertCustomer = document.getElementById('posCreditCustomerAlert');
        if (!selectedCustomer.id || selectedCustomer.document === '222222222222') {
            if (alertCustomer) alertCustomer.classList.remove('d-none');
        } else {
            if (alertCustomer) alertCustomer.classList.add('d-none');
        }
    }

    const modalEl = document.getElementById('customerModal');
    if (modalEl) {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }
}

function onPosLateralCustomerSelect(sel) {
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    const id = sel.value ? parseInt(sel.value, 10) : null;
    const name = opt.dataset.name || 'CONSUMIDOR FINAL';
    const doc = opt.dataset.doc || '222222222222';
    const phone = opt.dataset.phone || '';
    selectCustomer(id, name, doc, phone);
}

function filterPosLateralCustomerSelect(query) {
    const sel = document.getElementById('posCustomerLateralSelect');
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
        onPosLateralCustomerSelect(sel);
    }
}

function resetToFinalConsumer() {
    selectCustomer(null, "{{ $defaultCustomer['name'] }}", "{{ $defaultCustomer['document'] }}");
    const searchInput = document.getElementById('posCustomerSearch');
    if (searchInput) searchInput.value = '';
    const resultsBox = document.getElementById('posCustomerResults');
    if (resultsBox) resultsBox.classList.add('d-none');
    const payCustSearch = document.getElementById('payModalCustomerSearch');
    if (payCustSearch) payCustSearch.value = '';
    const lateralSel = document.getElementById('posCustomerLateralSelect');
    if (lateralSel) lateralSel.value = '';
}

function filterPayModalCustomerSelect(query) {
    const sel = document.getElementById('payModalCustomerSelect');
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
        onPayModalCustomerSelectChange(sel);
    }
}

function onPayModalCustomerSelectChange(sel) {
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    const id = sel.value ? parseInt(sel.value, 10) : null;
    const name = opt.dataset.name || 'CONSUMIDOR FINAL';
    const doc = opt.dataset.doc || '222222222222';
    const phone = opt.dataset.phone || '';

    selectedCustomer = { id, name, document: doc, phone };

    // Sincronizar en chip de barra lateral
    const dispName = document.getElementById('displayCustomerName');
    if (dispName) dispName.textContent = name;
    const dispDoc = document.getElementById('displayCustomerDoc');
    if (dispDoc) dispDoc.textContent = 'CC/NIT: ' + doc;
    const resetBtn = document.getElementById('btnPosResetCustomer');
    if (resetBtn) {
        if (id || (doc && doc !== '222222222222')) {
            resetBtn.classList.remove('d-none');
        } else {
            resetBtn.classList.add('d-none');
        }
    }

    // Si crédito está activado, verificar validación de inmediato
    const creditSw = document.getElementById('posCreditSwitch');
    if (creditSw && creditSw.checked) {
        const alertCustomer = document.getElementById('posCreditCustomerAlert');
        if (!selectedCustomer.id || selectedCustomer.document === '222222222222') {
            if (alertCustomer) alertCustomer.classList.remove('d-none');
        } else {
            if (alertCustomer) alertCustomer.classList.add('d-none');
        }
    }
}

function saveCustomCustomer() {
    const nameInput = document.getElementById('customClientName');
    const name = nameInput.value.trim();
    const doc = document.getElementById('customClientDoc').value.trim();
    if (!name) {
        nameInput.classList.add('is-invalid');
        nameInput.focus();
        califitNotify('Por favor digita al menos el nombre del cliente para continuar.', 'warning', 'Dato Requerido');
        return;
    }
    nameInput.classList.remove('is-invalid');
    selectCustomer(null, name, doc || '222222222222');
}

// MODAL DE COBRO Y CIERRE DE VENTA
function openPaymentModal() {
    const { total, subtotal, discountVal } = calculateTotals();
    if (cart.length === 0 || total <= 0) {
        if (typeof califitNotify === 'function') {
            califitNotify('Debes agregar al menos un plato o producto a la comanda antes de cobrar.', 'warning', 'Comanda Vacía');
        } else {
            alert('Debes agregar al menos un plato o producto a la comanda antes de cobrar.');
        }
        return;
    }

    // Sincronizar descuento del panel lateral con el modal
    const sidebarInp = document.getElementById('discountPercent');
    const modalInp = document.getElementById('payModalDiscountPercent');
    if (sidebarInp && modalInp) {
        modalInp.value = sidebarInp.value || 0;
    }

    // Sincronizar fecha y hora actual en el modal
    const dateInput = document.getElementById('payModalDate');
    if (dateInput) {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        dateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    // Sincronizar selector de cliente en el modal
    const payCustSel = document.getElementById('payModalCustomerSelect');
    if (payCustSel) {
        payCustSel.value = selectedCustomer.id || '';
    }
    const payCustSearch = document.getElementById('payModalCustomerSearch');
    if (payCustSearch) payCustSearch.value = '';

    // Sincronizar resumen en modal
    const paySub = document.getElementById('payModalSubtotalDisplay');
    if (paySub) paySub.textContent = '$' + subtotal.toLocaleString('es-CO');
    const payDisc = document.getElementById('payModalDiscountDisplay');
    if (payDisc) payDisc.textContent = '-$' + discountVal.toLocaleString('es-CO');
    const payTot = document.getElementById('payModalTotal');
    if (payTot) payTot.textContent = '$' + total.toLocaleString('es-CO');

    // Inicializar con 1 línea de Efectivo por defecto cubriendo el total exacto
    paymentLines = [{
        method: 'cash',
        amount: total,
        cash_received: total,
        reference: ''
    }];

    // Resetear estrictamente switch de crédito
    const creditSw = document.getElementById('posCreditSwitch');
    if (creditSw) {
        creditSw.checked = false;
        togglePosCreditMode(false);
    }

    renderPaymentLines();

    const payModalEl = document.getElementById('paymentModal');
    if (payModalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(payModalEl);
        modal.show();
    }
}

function renderPaymentLines() {
    const container = document.getElementById('paymentLinesContainer');
    container.innerHTML = '';

    const { total } = calculateTotals();
    const covered = paymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const diff = total - covered;

    paymentLines.forEach((line, idx) => {
        const div = document.createElement('div');
        div.className = 'p-2.5 bg-white rounded-3 border shadow-sm mb-2';

        let thirdFieldHtml = '';
        if (line.method === 'cash') {
            thirdFieldHtml = `
                <label class="form-label d-block text-muted mb-1 text-truncate" style="font-size: 0.7rem; font-weight: 600;" title="Dinero en efectivo entregado por el cliente">
                    CLIENTE ENTREGA
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted fw-semibold">$</span>
                    <input type="number" class="form-control text-end fw-bold text-dark" 
                           placeholder="Entregado" 
                           value="${line.cash_received !== null && line.cash_received !== undefined ? line.cash_received : ''}" 
                           oninput="updatePaymentCashReceived(${idx}, this.value)" 
                           title="Dinero entregado por el cliente (para calcular vuelto)">
                </div>
            `;
        } else if (line.method === 'card') {
            thirdFieldHtml = `
                <label class="form-label d-block text-muted mb-1 text-truncate" style="font-size: 0.7rem; font-weight: 600;" title="N° de Aprobación o Voucher del datáfono">
                    N° VOUCHER / APROB.
                </label>
                <input type="text" class="form-control form-control-sm" 
                       placeholder="Ej. Aprob. 4589" 
                       value="${line.reference || ''}" 
                       oninput="updatePaymentRef(${idx}, this.value)" 
                       title="Número de comprobante o aprobación del datáfono">
            `;
        } else if (line.method === 'transfer') {
            thirdFieldHtml = `
                <label class="form-label d-block text-muted mb-1 text-truncate" style="font-size: 0.7rem; font-weight: 600;" title="N° de Comprobante / Transacción Nequi o Bancolombia">
                    N° COMPROBANTE
                </label>
                <input type="text" class="form-control form-control-sm" 
                       placeholder="Ej. Nequi #9021" 
                       value="${line.reference || ''}" 
                       oninput="updatePaymentRef(${idx}, this.value)" 
                       title="Número de comprobante o referencia de transferencia">
            `;
        } else {
            thirdFieldHtml = `
                <label class="form-label d-block text-muted mb-1 text-truncate" style="font-size: 0.7rem; font-weight: 600;" title="N° de Soporte o Detalle del pago">
                    N° SOPORTE / DETALLE
                </label>
                <input type="text" class="form-control form-control-sm" 
                       placeholder="Ej. Bono / Vale #12" 
                       value="${line.reference || ''}" 
                       oninput="updatePaymentRef(${idx}, this.value)" 
                       title="Detalle o soporte del pago">
            `;
        }

        div.innerHTML = `
            <div class="row g-2 align-items-center">
                <div class="col-4">
                    <label class="form-label d-block text-muted mb-1" style="font-size: 0.7rem; font-weight: 600;">MÉTODO DE PAGO</label>
                    <select class="form-select form-select-sm fw-semibold" onchange="updatePaymentMethod(${idx}, this.value)">
                        <option value="cash" ${line.method === 'cash' ? 'selected' : ''}>💵 Efectivo</option>
                        <option value="transfer" ${line.method === 'transfer' ? 'selected' : ''}>📲 Transferencia / Nequi</option>
                        <option value="card" ${line.method === 'card' ? 'selected' : ''}>💳 Tarjeta / Datáfono</option>
                        <option value="other" ${line.method === 'other' ? 'selected' : ''}>📄 Otro Medio</option>
                    </select>
                </div>
                <div class="col-4">
                    <label class="form-label d-block text-muted mb-1" style="font-size: 0.7rem; font-weight: 600;">MONTO A COBRAR</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light fw-bold text-success">$</span>
                        <input type="number" class="form-control text-end fw-bold text-dark pos-pay-amount-input" 
                               data-idx="${idx}"
                               value="${line.amount}" step="0.01" min="0" placeholder="0" 
                               oninput="updatePaymentAmount(${idx}, this.value)" 
                               title="Monto a cobrar con este método">
                    </div>
                </div>
                <div class="col-3">
                    ${thirdFieldHtml}
                </div>
                <div class="col-1 text-center pt-3">
                    ${paymentLines.length > 1 ? `
                        <button type="button" class="btn btn-outline-danger btn-sm p-0 rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 26px; height: 26px;" onclick="removePaymentLine(${idx})" title="Eliminar método">
                            <i class="bi bi-trash" style="font-size: 0.75rem;"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
            ${diff > 0.01 && line.amount < total ? `
                <div class="mt-1 pt-1 border-top border-light d-flex justify-content-end">
                    <button type="button" class="btn btn-link btn-xs p-0 text-success text-decoration-none fw-semibold" style="font-size: 0.72rem;" onclick="assignRemainingToPaymentLine(${idx})">
                        <i class="bi bi-magic me-1"></i> Asignar restante ($${diff.toLocaleString('es-CO')}) a este método
                    </button>
                </div>
            ` : ''}
        `;
        container.appendChild(div);
    });

    document.getElementById('btnAddPaymentLine').disabled = paymentLines.length >= 5;
    recalculatePaymentSummary();
}

function addPaymentLine() {
    if (paymentLines.length >= 5) return;
    const { total } = calculateTotals();
    const covered = paymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const remaining = Math.max(0, total - covered);
    const hasCash = paymentLines.some(p => p.method === 'cash');
    const newMethod = hasCash ? 'transfer' : 'cash';

    if (remaining > 0.01) {
        // Hay saldo restante por cubrir: asignar automáticamente
        paymentLines.push({
            method: newMethod,
            amount: remaining,
            cash_received: newMethod === 'cash' ? remaining : null,
            reference: ''
        });
    } else {
        // Si el total ya estaba cubierto al 100% y había 1 sola línea previa, dividir 50/50 automáticamente
        if (paymentLines.length === 1) {
            const half1 = Math.round(total / 2);
            const half2 = total - half1;
            paymentLines[0].amount = half1;
            if (paymentLines[0].method === 'cash') {
                paymentLines[0].cash_received = half1;
            }
            paymentLines.push({
                method: newMethod,
                amount: half2,
                cash_received: newMethod === 'cash' ? half2 : null,
                reference: ''
            });
        } else {
            paymentLines.push({
                method: newMethod,
                amount: 0,
                cash_received: newMethod === 'cash' ? 0 : null,
                reference: ''
            });
        }
    }
    renderPaymentLines();
}

function removePaymentLine(idx) {
    paymentLines.splice(idx, 1);
    const { total } = calculateTotals();
    // Si queda 1 sola línea, cubrir el total completo automáticamente
    if (paymentLines.length === 1) {
        paymentLines[0].amount = total;
        if (paymentLines[0].method === 'cash') {
            paymentLines[0].cash_received = total;
        }
    }
    renderPaymentLines();
}

function updatePaymentMethod(idx, method) {
    if (method === 'cash' && paymentLines.filter((p, i) => i !== idx && p.method === 'cash').length > 0) {
        califitNotify('Solo se permite una sola línea de pago en efectivo.', 'warning', 'Método de Pago');
        renderPaymentLines();
        return;
    }
    paymentLines[idx].method = method;
    paymentLines[idx].cash_received = method === 'cash' ? paymentLines[idx].amount : null;
    renderPaymentLines();
}

function updatePaymentAmount(idx, val) {
    const { total } = calculateTotals();
    const newAmt = parseFloat(window.unformatCOP ? window.unformatCOP(val) : val) || 0;
    paymentLines[idx].amount = newAmt;

    if (paymentLines[idx].method === 'cash') {
        if (!paymentLines[idx].cash_received || paymentLines[idx].cash_received < newAmt) {
            paymentLines[idx].cash_received = newAmt;
        }
    }

    // Si hay exactamente 2 métodos, balancear automáticamente el restante en la otra línea
    if (paymentLines.length === 2) {
        const otherIdx = idx === 0 ? 1 : 0;
        const otherRemaining = Math.max(0, total - newAmt);
        paymentLines[otherIdx].amount = otherRemaining;
        if (paymentLines[otherIdx].method === 'cash') {
            paymentLines[otherIdx].cash_received = otherRemaining;
        }

        // Actualizar visualmente el input de la otra línea en el DOM para no perder el foco
        const inputs = document.querySelectorAll('.pos-pay-amount-input');
        inputs.forEach(inp => {
            if (parseInt(inp.dataset.idx) === otherIdx) {
                inp.value = otherRemaining;
            }
        });
    }

    recalculatePaymentSummary();
}

function assignRemainingToPaymentLine(idx) {
    const { total } = calculateTotals();
    const otherCovered = paymentLines.reduce((acc, p, i) => i !== idx ? acc + (parseFloat(p.amount) || 0) : acc, 0);
    const needed = Math.max(0, total - otherCovered);
    paymentLines[idx].amount = needed;
    if (paymentLines[idx].method === 'cash') {
        paymentLines[idx].cash_received = needed;
    }
    renderPaymentLines();
}

function updatePaymentCashReceived(idx, val) {
    paymentLines[idx].cash_received = parseFloat(window.unformatCOP ? window.unformatCOP(val) : val) || 0;
    recalculatePaymentSummary();
}

function updatePaymentRef(idx, val) {
    paymentLines[idx].reference = val;
}

function setCashQuick(amountOrExact) {
    const cashLine = paymentLines.find(p => p.method === 'cash');
    if (!cashLine) return;

    if (amountOrExact === 'exact') {
        cashLine.cash_received = cashLine.amount;
    } else {
        cashLine.cash_received = parseFloat(amountOrExact);
    }
    renderPaymentLines();
}

function recalculatePaymentSummary() {
    const { total } = calculateTotals();
    const covered = paymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const diff = total - covered;

    const coveredEl = document.getElementById('payModalCovered');
    if (coveredEl) coveredEl.textContent = '$' + covered.toLocaleString('es-CO');

    const diffEl = document.getElementById('payModalDiff');
    if (diffEl) {
        if (Math.abs(diff) < 0.01) {
            diffEl.innerHTML = '<span class="fw-bold text-success"><i class="bi bi-check-circle me-1"></i>$0 (Exacto)</span>';
        } else if (diff > 0) {
            diffEl.innerHTML = '<span class="fw-bold text-danger"><i class="bi bi-exclamation-circle me-1"></i>Faltan $' + diff.toLocaleString('es-CO') + '</span>';
        } else {
            diffEl.innerHTML = '<span class="fw-bold text-primary"><i class="bi bi-info-circle me-1"></i>Excedente $' + Math.abs(diff).toLocaleString('es-CO') + '</span>';
        }
    }

    // Calcular cambio del efectivo
    const cashLine = paymentLines.find(p => p.method === 'cash');
    const changeBox = document.getElementById('changeIndicatorBox');
    const changeDetails = document.getElementById('changeDetailsBox');

    if (changeBox && changeDetails) {
        if (cashLine) {
            const received = parseFloat(cashLine.cash_received) || 0;
            const toPay = parseFloat(cashLine.amount) || 0;
            const change = received - toPay;

            if (change >= 0) {
                changeDetails.innerHTML = `
                    <div class="small fw-bold text-muted mb-1 text-uppercase">Cambio / Vuelto al Cliente</div>
                    <h4 class="fw-black mb-0 text-success">$${change.toLocaleString('es-CO')}</h4>
                `;
                changeBox.style.backgroundColor = '#ecfdf5';
                changeBox.style.borderColor = '#a7f3d0';
            } else {
                changeDetails.innerHTML = `
                    <div class="small fw-bold text-danger mb-1 text-uppercase"><i class="bi bi-exclamation-triangle me-1"></i>Efectivo Insuficiente</div>
                    <h4 class="fw-black mb-0 text-danger">Faltan $${Math.abs(change).toLocaleString('es-CO')}</h4>
                `;
                changeBox.style.backgroundColor = '#fef2f2';
                changeBox.style.borderColor = '#fca5a5';
            }
        } else {
            changeDetails.innerHTML = `
                <div class="small fw-bold text-muted mb-1 text-uppercase">Pago Electrónico</div>
                <h4 class="fw-black mb-0 text-secondary">$0 <span class="fs-6 fw-normal">(Sin vuelto)</span></h4>
            `;
            changeBox.style.backgroundColor = '#f8fafc';
            changeBox.style.borderColor = '#e2e8f0';
        }
    }
}

function togglePosCreditMode(isCredit) {
    const creditFields = document.getElementById('posCreditFields');
    const stdPayments = document.getElementById('posStandardPaymentSection');
    const btn = document.getElementById('btnSubmitSale');
    const alertCustomer = document.getElementById('posCreditCustomerAlert');

    if (isCredit) {
        if (creditFields) creditFields.classList.remove('d-none');
        if (stdPayments) stdPayments.classList.add('d-none');
        if (btn) btn.innerHTML = '<i class="bi bi-wallet2 fs-5"></i> <span class="fs-6">Registrar Venta a Crédito</span>';

        // Validar si el cliente actual es consumidor final
        if (!selectedCustomer.id || selectedCustomer.document === '222222222222') {
            if (alertCustomer) alertCustomer.classList.remove('d-none');
        } else {
            if (alertCustomer) alertCustomer.classList.add('d-none');
        }
    } else {
        if (creditFields) creditFields.classList.add('d-none');
        if (stdPayments) stdPayments.classList.remove('d-none');
        if (btn) btn.innerHTML = '<i class="bi bi-check2-circle fs-5"></i> <span class="fs-6">Registrar Venta</span>';
        if (alertCustomer) alertCustomer.classList.add('d-none');
        if (typeof recalculatePaymentSummary === 'function') {
            recalculatePaymentSummary();
        }
    }
}

// ENVIAR VENTA ASÍNCRONA AL BACKEND
async function submitSale() {
    const isCredit = document.getElementById('posCreditSwitch').checked;
    const { total, discPct, delivery } = calculateTotals();

    if (isCredit) {
        if (!selectedCustomer.id || selectedCustomer.document === '222222222222') {
            califitNotify('Para otorgar crédito debe seleccionar un cliente registrado con nombre y teléfono. No permitido para Consumidor Final.', 'danger', 'Cliente Obligatorio');
            // Abrir modal de selección de cliente
            const custModal = new bootstrap.Modal(document.getElementById('customerModal'));
            custModal.show();
            return;
        }
    } else {
        const covered = paymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);

        if (Math.abs(total - covered) > 0.01) {
            califitNotify('El total cubierto por los métodos de pago debe coincidir exactamente con el total a pagar.', 'warning', 'Descuadre en Pagos');
            return;
        }

        const cashLine = paymentLines.find(p => p.method === 'cash');
        if (cashLine && (parseFloat(cashLine.cash_received) || 0) < cashLine.amount) {
            califitNotify('El dinero en efectivo recibido no puede ser inferior al monto asignado en la línea de efectivo.', 'warning', 'Efectivo Insuficiente');
            return;
        }
    }

    // Preparar payload estricto
    const itemsPayload = cart.map(item => {
        const data = {
            product_id: item.product_id,
            quantity: item.quantity
        };
        if (item.additions && item.additions.length > 0) {
            data.additions = item.additions.map(a => ({
                addition_id: a.addition_id,
                quantity: a.quantity
            }));
        }
        return data;
    });

    const notesEl = document.getElementById('posSaleNotes');
    const notesVal = notesEl ? notesEl.value.trim() : null;

    const payload = {
        customer_id: selectedCustomer.id,
        customer_name: selectedCustomer.name,
        customer_document: selectedCustomer.document,
        discount_percentage: discPct,
        delivery_fee: delivery,
        notes: notesVal || null,
        items: itemsPayload,
        is_credit: isCredit,
        credit_due_date: isCredit ? document.getElementById('posCreditDueDate').value : null,
        payments: isCredit ? [] : paymentLines.map(p => ({
            method: p.method,
            amount: p.amount,
            reference: p.reference || null,
            cash_received: p.method === 'cash' ? p.cash_received : null
        }))
    };

    const btn = document.getElementById('btnSubmitSale');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

    try {
        const res = await fetch("{{ route('pos.store') }}", {
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
            // Cerrar modal
            const modalEl = document.getElementById('paymentModal');
            const modal = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
            if (modal) modal.hide();

            // Abrir ventana de impresión térmica
            const printUrl = `/prints/sales/${data.sale_id}/receipt`;
            window.open(printUrl, '_blank', 'width=340,height=620');

            // Resetear comanda y campos del formulario
            cart = [];
            renderCart();
            document.getElementById('discountPercent').value = 0;
            const payDiscEl = document.getElementById('payModalDiscountPercent');
            if (payDiscEl) payDiscEl.value = 0;
            if (notesEl) notesEl.value = '';
            resetToFinalConsumer();

            const creditSw = document.getElementById('posCreditSwitch');
            if (creditSw) {
                creditSw.checked = false;
                togglePosCreditMode(false);
            }

            califitNotify(`¡${data.message}! Consecutivo: ${data.invoice_number}`, 'success', 'Venta Registrada');
        } else {
            califitNotify('Error al registrar venta: ' + (data.message || JSON.stringify(data.errors || data)), 'danger', 'Error en Transacción');
        }
    } catch (err) {
        console.error(err);
        califitNotify('Ocurrió un error en la comunicación con el servidor.', 'danger', 'Error de Conexión');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle fs-5"></i><span class="fs-6">Registrar Venta</span>';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const payModalEl = document.getElementById('paymentModal');
    if (payModalEl) {
        payModalEl.addEventListener('show.bs.modal', () => {
            const creditSw = document.getElementById('posCreditSwitch');
            if (creditSw) {
                creditSw.checked = false;
                togglePosCreditMode(false);
            }
        });
        payModalEl.addEventListener('hidden.bs.modal', () => {
            const creditSw = document.getElementById('posCreditSwitch');
            if (creditSw) {
                creditSw.checked = false;
                togglePosCreditMode(false);
            }
        });
    }
});
</script>
@endsection
