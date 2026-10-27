@extends('layouts.app')

@section('title', 'Cartera y Cuentas por Cobrar | SaborCaliFit')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold mb-0 text-dark">
            <i class="bi bi-wallet2 text-danger me-2"></i>Cartera &amp; Cuentas por Cobrar (Fiados)
        </h3>
        <p class="text-muted small mb-0">Gestión de créditos otorgados a clientes y registro de recaudos</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-people me-1"></i> Directorio de Clientes
        </a>
    </div>
</div>

{{-- Tarjetas Métricas Superiores --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 bg-danger bg-opacity-10 border border-danger border-opacity-25 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-danger small fw-bold text-uppercase">Total por Cobrar en Cartera</span>
                    <h2 class="fw-bold mb-0 text-danger mt-1">
                        ${{ number_format($totalReceivable, 0, ',', '.') }}
                    </h2>
                    <small class="text-muted">Saldo diferido pendiente de cobro</small>
                </div>
                <div class="rounded-circle p-3 bg-danger text-white">
                    <i class="bi bi-wallet2 fs-3"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Facturas Fiadas Pendientes</span>
                    <h2 class="fw-bold mb-0 text-dark mt-1">
                        {{ $pendingSales->total() }}
                    </h2>
                    <small class="text-muted">Pendientes de liquidación total</small>
                </div>
                <div class="rounded-circle p-3 bg-warning bg-opacity-10 text-dark">
                    <i class="bi bi-receipt-cutoff fs-3"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Clientes con Deuda Activa</span>
                    <h2 class="fw-bold mb-0 text-dark mt-1">
                        {{ $customersWithCredit->count() }}
                    </h2>
                    <small class="text-muted">Con saldo mayor a $0</small>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px; background-color: #10B981; color: #FFFFFF;">
                    <i class="bi bi-people-fill fs-3 text-white"></i>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $tabParam = request('tab', $activeTab ?? 'grouped');
    $isGroupedActive = $tabParam === 'grouped';
    $isInvoicesActive = !$isGroupedActive;
    $hasFilters = request()->has('search') || request()->has('customer_id');
@endphp

{{-- BARRA DE FILTROS Y BÚSQUEDA AGRUPADA --}}
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('credit.index') }}" class="row g-2 align-items-end" id="filtersCreditForm">
            <input type="hidden" name="tab" value="{{ $isGroupedActive ? 'grouped' : 'invoices' }}" id="activeCreditTabInput">

            <div class="col-12 col-md-5">
                <label class="form-label small fw-bold text-muted mb-1 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Búsqueda Rápida</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" 
                           name="search" 
                           class="form-control form-control-sm border-start-0 ps-0" 
                           placeholder="Cliente, documento o factura #FAC..." 
                           value="{{ request('search') }}">
                </div>
            </div>

            <div class="col-12 col-md-5">
                <label class="form-label small fw-bold text-muted mb-1 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Filtrar por Cliente con Deuda</label>
                <select name="customer_id" class="form-select form-select-sm" onchange="document.getElementById('filtersCreditForm').submit()">
                    <option value="">Todos los clientes con deuda ({{ $customersWithCredit->count() }})</option>
                    @foreach($customersWithCredit as $c)
                        <option value="{{ $c->id }}" {{ (string) request('customer_id') === (string) $c->id ? 'selected' : '' }}>
                            {{ $c->name }} ({{ $c->sales->count() }} facturas — ${{ number_format($c->sales->sum('credit_pending_amount'), 0, ',', '.') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-califit btn-sm flex-grow-1 fw-semibold py-1.5" style="background-color: #10B981; border-color: #10B981; color: #FFFFFF;">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                @if($hasFilters)
                    <a href="{{ route('credit.index', ['tab' => $isGroupedActive ? 'grouped' : 'invoices']) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" title="Limpiar filtros" style="width: 36px;">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- BANNER INFORMATIVO CUANDO HAY UN CLIENTE FILTRADO --}}
@if($selectedCustomer)
    <div class="alert border-0 shadow-sm rounded-4 p-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background-color: #ECFDF5; border: 1px solid #A7F3D0 !important;">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 44px; height: 44px; background-color: #10B981; color: #FFFFFF;">
                <i class="bi bi-person-fill fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted small">Mostrando facturas pendientes de:</span>
                    <h6 class="fw-bold text-dark mb-0 fs-6">{{ $selectedCustomer->name }}</h6>
                    <span class="badge rounded-pill px-2.5 py-1 text-white fw-bold" style="background-color: #DC2626;">
                        {{ $pendingSales->total() }} facturas pendientes
                    </span>
                </div>
                <div class="small text-muted mt-0.5">
                    <i class="bi bi-card-text me-1"></i>Doc: {{ $selectedCustomer->document ?: 'Sin documento' }} &bull;
                    <i class="bi bi-telephone me-1"></i>Tel: {{ $selectedCustomer->phone ?: '—' }} &bull;
                    Saldo pendiente reunido: <strong class="text-danger fw-bold">${{ number_format($pendingSales->sum('credit_pending_amount'), 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('customers.show', $selectedCustomer) }}" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold" style="border-color: #10B981; color: #059669;">
                <i class="bi bi-person me-1"></i> Ficha Completa
            </a>
            <a href="{{ route('credit.index', ['tab' => $isGroupedActive ? 'grouped' : 'invoices']) }}" class="btn btn-sm btn-light border rounded-pill px-3 text-secondary" title="Quitar filtro de cliente">
                <i class="bi bi-x-circle me-1"></i> Ver Todos los Clientes
            </a>
        </div>
    </div>
@endif

{{-- SELECTOR DE PESTAÑAS (SEGMENTED CONTROL CALIFIT) --}}
<div class="d-flex justify-content-start mb-3">
    <div class="p-1.5 rounded-pill bg-light border shadow-sm d-inline-flex gap-1" role="tablist">
        <button class="btn btn-sm rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2 border-0 transition-all tab-switcher-btn {{ $isGroupedActive ? 'active-tab' : 'inactive-tab' }}" 
                id="grouped-credit-tab" 
                type="button" 
                role="tab"
                onclick="switchCreditTab('grouped')"
                style="{{ $isGroupedActive ? 'background-color: #10B981; color: #FFFFFF;' : 'background-color: transparent; color: #4B5563;' }}">
            <i class="bi bi-people-fill"></i>
            <span>Agrupado por Cliente</span>
            <span class="badge rounded-pill px-2 py-0.5 ms-1" id="grouped-count-badge" style="{{ $isGroupedActive ? 'background-color: rgba(255,255,255,0.25); color: #FFFFFF;' : 'background-color: #10B981; color: #FFFFFF;' }}">
                {{ $groupedCustomers->count() }}
            </span>
        </button>

        <button class="btn btn-sm rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2 border-0 transition-all tab-switcher-btn {{ $isInvoicesActive ? 'active-tab' : 'inactive-tab' }}" 
                id="invoices-credit-tab" 
                type="button" 
                role="tab"
                onclick="switchCreditTab('invoices')"
                style="{{ $isInvoicesActive ? 'background-color: #10B981; color: #FFFFFF;' : 'background-color: transparent; color: #4B5563;' }}">
            <i class="bi bi-receipt-cutoff"></i>
            <span>Facturas Individuales</span>
            <span class="badge rounded-pill px-2 py-0.5 ms-1" id="invoices-count-badge" style="{{ $isInvoicesActive ? 'background-color: rgba(255,255,255,0.25); color: #FFFFFF;' : 'background-color: #6B7280; color: #FFFFFF;' }}">
                {{ $pendingSales->total() }}
            </span>
        </button>
    </div>
</div>

{{-- CONTENEDOR DE PESTAÑAS --}}
<div class="tab-content" id="creditTabContent">
    {{-- PESTAÑA 1: AGRUPADO POR CLIENTE (REUNIÓN DE FACTURAS) --}}
    <div class="tab-pane fade {{ $isGroupedActive ? 'show active' : '' }}" id="grouped-credit-pane" role="tabpanel" aria-labelledby="grouped-credit-tab">
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-people-fill me-1" style="color: #10B981;"></i>Clientes con Facturas Pendientes Reunidas
                    </h5>
                    <span class="badge rounded-pill px-2.5 py-1 text-white fw-bold" style="background-color: #10B981;">
                        {{ $groupedCustomers->count() }} clientes
                    </span>
                </div>
                <small class="text-muted">Despliega o filtra cada cliente para reunir y gestionar todas sus facturas</small>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted">
                            <th class="ps-3 py-3">Cliente</th>
                            <th class="py-3 text-center">Facturas Pendientes</th>
                            <th class="py-3 text-end">Total Fiado</th>
                            <th class="py-3 text-end">Total Abonado</th>
                            <th class="py-3 text-end">Saldo Total Pendiente</th>
                            <th class="py-3 text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groupedCustomers as $customer)
                            @php
                                $customerTotalCredit = $customer->sales->sum('total');
                                $customerTotalPaid = $customer->sales->sum('credit_paid_amount');
                                $customerTotalPending = $customer->sales->sum('credit_pending_amount');
                                $customerInvoicesCount = $customer->sales->count();
                            @endphp
                            <tr style="border-bottom: 1px solid #F3F4F6;">
                                <td class="ps-3 py-3">
                                    <div class="fw-bold text-dark fs-6">{{ $customer->name }}</div>
                                    <div class="small text-muted font-monospace">
                                        <i class="bi bi-card-text me-1"></i>{{ $customer->document ?: 'Sin documento' }}
                                        @if($customer->phone)
                                            &bull; <i class="bi bi-telephone me-1"></i>{{ $customer->phone }}
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 text-center">
                                    <span class="badge rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="background-color: #F59E0B; color: #111827; font-size: 0.8rem;">
                                        <i class="bi bi-receipt me-1"></i>{{ $customerInvoicesCount }} {{ $customerInvoicesCount === 1 ? 'factura' : 'facturas' }}
                                    </span>
                                </td>
                                <td class="py-3 text-end fw-semibold text-secondary">
                                    ${{ number_format($customerTotalCredit, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end text-success fw-semibold">
                                    ${{ number_format($customerTotalPaid, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end fw-bold text-danger fs-6">
                                    ${{ number_format($customerTotalPending, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1 flex-wrap">
                                        <button class="btn btn-sm btn-outline-success rounded-pill px-3 fw-bold d-inline-flex align-items-center gap-1 shadow-sm"
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#collapseCustomerInvoices{{ $customer->id }}" 
                                                aria-expanded="false" 
                                                style="border-color: #10B981; color: #059669;">
                                            <i class="bi bi-receipt-cutoff"></i> Reunir Facturas ({{ $customerInvoicesCount }})
                                        </button>
                                        <a href="{{ route('credit.index', ['customer_id' => $customer->id, 'tab' => 'invoices']) }}" 
                                           class="btn btn-sm btn-light border rounded-pill px-2.5" 
                                           title="Filtrar solo facturas de este cliente">
                                            <i class="bi bi-funnel text-secondary"></i>
                                        </a>
                                        <a href="{{ route('customers.show', $customer) }}" 
                                           class="btn btn-sm btn-light border rounded-pill px-2.5" 
                                           title="Ver Ficha y Cartera General">
                                            <i class="bi bi-person text-secondary"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            {{-- FILA COLAPSABLE CON TODAS LAS FACTURAS REUNIDAS DEL CLIENTE --}}
                            <tr class="collapse bg-light bg-opacity-50" id="collapseCustomerInvoices{{ $customer->id }}">
                                <td colspan="6" class="p-3">
                                    <div class="card border rounded-4 shadow-sm overflow-hidden bg-white">
                                        <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                                            <span class="small fw-bold text-dark">
                                                <i class="bi bi-folder-check text-success me-1"></i>
                                                Facturas pendientes de <strong>{{ $customer->name }}</strong> ({{ $customerInvoicesCount }})
                                            </span>
                                            <span class="small text-danger fw-bold">
                                                Saldo acumulado: ${{ number_format($customerTotalPending, 0, ',', '.') }}
                                            </span>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead class="table-light small text-uppercase text-muted">
                                                    <tr>
                                                        <th class="ps-3 py-2">Factura</th>
                                                        <th class="py-2">Emisión</th>
                                                        <th class="py-2">Fecha Límite</th>
                                                        <th class="py-2 text-end">Total Venta</th>
                                                        <th class="py-2 text-end">Abonado</th>
                                                        <th class="py-2 text-end">Saldo Pendiente</th>
                                                        <th class="py-2 text-end pe-3">Acción</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($customer->sales as $sale)
                                                        <tr>
                                                            <td class="ps-3 py-2 font-monospace fw-bold text-dark">
                                                                {{ $sale->invoice_number }}
                                                            </td>
                                                            <td class="py-2 small text-muted">
                                                                {{ $sale->created_at->format('d/m/Y H:i') }}
                                                            </td>
                                                            <td class="py-2">
                                                                @if($sale->credit_due_date)
                                                                    <span class="badge {{ \Carbon\Carbon::parse($sale->credit_due_date)->isPast() ? 'bg-danger text-white' : 'bg-light text-dark border' }} rounded-pill px-2">
                                                                        {{ \Carbon\Carbon::parse($sale->credit_due_date)->format('d/m/Y') }}
                                                                    </span>
                                                                @else
                                                                    <span class="text-muted small">Sin plazo</span>
                                                                @endif
                                                            </td>
                                                            <td class="py-2 text-end fw-semibold">
                                                                ${{ number_format($sale->total, 0, ',', '.') }}
                                                            </td>
                                                            <td class="py-2 text-end text-success">
                                                                ${{ number_format($sale->credit_paid_amount, 0, ',', '.') }}
                                                            </td>
                                                            <td class="py-2 text-end fw-bold text-danger">
                                                                ${{ number_format($sale->credit_pending_amount, 0, ',', '.') }}
                                                            </td>
                                                            <td class="py-2 text-end pe-3">
                                                                <div class="d-flex justify-content-end gap-1">
                                                                    <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold"
                                                                            onclick="openPaymentModal({{ $sale->id }}, {{ $sale->credit_pending_amount }}, '{{ $sale->invoice_number }}', '{{ addslashes($customer->name) }}')">
                                                                        <i class="bi bi-wallet2 me-1"></i> Cobrar
                                                                    </button>
                                                                    <a href="{{ route('prints.sales.receipt', $sale) }}" target="_blank" class="btn btn-sm btn-light border rounded-pill px-2" title="Imprimir Comprobante">
                                                                        <i class="bi bi-printer text-secondary"></i>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-check-circle text-success fs-1 d-block mb-2"></i>
                                    <strong class="d-block text-dark">¡No se encontraron clientes con facturas pendientes!</strong>
                                    <small>No hay registros que coincidan con los criterios de búsqueda o filtro.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- PESTAÑA 2: TODAS LAS FACTURAS INDIVIDUALES --}}
    <div class="tab-pane fade {{ $isInvoicesActive ? 'show active' : '' }}" id="invoices-credit-pane" role="tabpanel" aria-labelledby="invoices-credit-tab">
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-receipt-cutoff me-1 text-danger"></i>Facturas Pendientes de Cobro
                    </h5>
                    @if($selectedCustomer)
                        <span class="badge rounded-pill px-2.5 py-1 text-white fw-bold" style="background-color: #10B981;">
                            Filtrado: {{ $selectedCustomer->name }}
                        </span>
                    @endif
                </div>
                <span class="small text-muted">Mostrando {{ $pendingSales->count() }} de {{ $pendingSales->total() }} facturas</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted">
                            <th class="ps-3 py-3">Factura</th>
                            <th class="py-3">Cliente</th>
                            <th class="py-3">Teléfono</th>
                            <th class="py-3">Emisión</th>
                            <th class="py-3">Fecha Límite</th>
                            <th class="py-3 text-end">Total Venta</th>
                            <th class="py-3 text-end">Abonado</th>
                            <th class="py-3 text-end">Saldo Pendiente</th>
                            <th class="py-3 text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingSales as $sale)
                            <tr style="border-bottom: 1px solid #F3F4F6;">
                                <td class="ps-3 py-3 font-monospace fw-bold text-dark">
                                    {{ $sale->invoice_number }}
                                </td>
                                <td class="py-3">
                                    @if($sale->customer)
                                        <a href="{{ route('credit.index', ['customer_id' => $sale->customer->id, 'tab' => 'grouped']) }}" class="fw-bold text-dark text-decoration-none" title="Ver todas las facturas de este cliente agrupadas">
                                            {{ $sale->customer->name }}
                                        </a>
                                        <div class="small text-muted font-monospace">{{ $sale->customer->document ?: 'Sin documento' }}</div>
                                    @else
                                        <span class="text-muted">{{ $sale->customer_name }}</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <small class="text-dark">{{ $sale->customer->phone ?? '—' }}</small>
                                </td>
                                <td class="py-3">
                                    <small class="text-muted">{{ $sale->created_at->format('d/m/Y H:i') }}</small>
                                </td>
                                <td class="py-3">
                                    @if($sale->credit_due_date)
                                        <span class="badge {{ \Carbon\Carbon::parse($sale->credit_due_date)->isPast() ? 'bg-danger text-white' : 'bg-light text-dark border' }} rounded-pill px-2.5 py-1">
                                            {{ \Carbon\Carbon::parse($sale->credit_due_date)->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="text-muted small">Sin plazo</span>
                                    @endif
                                </td>
                                <td class="py-3 text-end fw-semibold">
                                    ${{ number_format($sale->total, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end text-success">
                                    ${{ number_format($sale->credit_paid_amount, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end fw-bold text-danger fs-6">
                                    ${{ number_format($sale->credit_pending_amount, 0, ',', '.') }}
                                </td>
                                <td class="py-3 text-end pe-3">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold"
                                                onclick="openPaymentModal({{ $sale->id }}, {{ $sale->credit_pending_amount }}, '{{ $sale->invoice_number }}', '{{ addslashes($sale->customer->name ?? $sale->customer_name) }}')">
                                            <i class="bi bi-wallet2 me-1"></i> Cobrar
                                        </button>
                                        <a href="{{ route('prints.sales.receipt', $sale) }}" target="_blank" class="btn btn-sm btn-light border rounded-pill px-2" title="Imprimir Comprobante Térmico 58mm">
                                            <i class="bi bi-printer text-secondary"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="bi bi-check-circle text-success fs-1 d-block mb-2"></i>
                                    <strong class="d-block text-dark">¡No se encontraron facturas pendientes!</strong>
                                    <small>No hay registros que coincidan con la búsqueda o filtro aplicado.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($pendingSales->hasPages())
                <div class="p-3 border-top d-flex justify-content-center">
                    {{ $pendingSales->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- MODAL PARA REGISTRAR ABONO / LIQUIDAR DEUDA --}}
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('credit.pay') }}" method="POST" id="formCreditPayment" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <input type="hidden" name="sale_id" id="modal_sale_id" value="">

            <div class="modal-header border-bottom px-4 pt-4 pb-3">
                <h5 class="modal-title fw-bold text-dark" id="paymentModalLabel">
                    <i class="bi bi-wallet2 text-success me-2"></i>Registrar Cobro de Cartera
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body px-4 py-3">
                <div class="p-3 bg-light rounded-4 mb-3 border">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Cliente:</span>
                        <strong class="text-dark" id="modal_customer_display">—</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Factura Fiada:</span>
                        <span class="font-monospace fw-bold text-dark" id="modal_invoice_display">—</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Saldo Pendiente:</span>
                        <span class="fw-bold text-danger fs-6" id="modal_pending_display">$0</span>
                    </div>
                </div>

                {{-- Monto a Abonar --}}
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-bold text-dark small mb-0">Monto del Cobro ($) *</label>
                        <button type="button" class="btn btn-sm btn-link p-0 text-success text-decoration-none fw-semibold" id="btnPayFull">
                            Cobrar Total
                        </button>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-white fw-bold">$</span>
                        <input type="number" name="amount" id="modal_amount" class="form-control form-control-lg fw-bold text-success" step="1" min="1" required>
                    </div>
                </div>

                {{-- Método de Pago --}}
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small mb-1">Método de Recaudo *</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="method" id="pay_cash" value="cash" checked onchange="togglePaymentFields()">
                        <label class="btn btn-outline-secondary py-2" for="pay_cash">
                            <i class="bi bi-cash me-1"></i> Efectivo
                        </label>

                        <input type="radio" class="btn-check" name="method" id="pay_transfer" value="transfer" onchange="togglePaymentFields()">
                        <label class="btn btn-outline-secondary py-2" for="pay_transfer">
                            <i class="bi bi-phone me-1"></i> Nequi / Transf.
                        </label>

                        <input type="radio" class="btn-check" name="method" id="pay_card" value="card" onchange="togglePaymentFields()">
                        <label class="btn btn-outline-secondary py-2" for="pay_card">
                            <i class="bi bi-credit-card me-1"></i> Tarjeta
                        </label>
                    </div>
                </div>

                {{-- Efectivo Recibido y Cambio (Solo Cash) --}}
                <div id="cashFields" class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1">Efectivo Recibido</label>
                        <input type="number" name="cash_received" id="modal_cash_received" class="form-control" step="1" min="0" placeholder="$ Recibido" oninput="calculateChange()">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1">Cambio / Vueltos</label>
                        <input type="text" id="modal_change_display" class="form-control bg-light fw-bold" readonly value="$0">
                    </div>
                </div>

                {{-- Referencia / Voucher --}}
                <div class="mb-2">
                    <label class="form-label small text-muted mb-1">Referencia / Comprobante (Opcional)</label>
                    <input type="text" name="reference" id="modal_reference" class="form-control" placeholder="Ej: Aprobación datáfono o Nequi M1234">
                </div>

                <div class="alert alert-info py-2 px-3 rounded-3 small mb-0 mt-3 d-flex align-items-center">
                    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                    <div>
                        Este recaudo ingresará formalmente al <strong>Cuadre de Caja de hoy</strong> bajo el usuario en sesión.
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top px-4 py-3">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background-color: #10B981; border-color: #10B981;">
                    <i class="bi bi-check-lg me-1"></i> Registrar Recaudo
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentPendingAmount = 0;

function openPaymentModal(saleId, pendingAmount, invoiceNumber, customerName) {
    currentPendingAmount = pendingAmount;
    document.getElementById('modal_sale_id').value = saleId;
    document.getElementById('modal_customer_display').innerText = customerName;
    document.getElementById('modal_invoice_display').innerText = invoiceNumber;
    document.getElementById('modal_pending_display').innerText = '$' + new Intl.NumberFormat('es-CO').format(pendingAmount);
    
    const amountInput = document.getElementById('modal_amount');
    amountInput.value = pendingAmount;
    amountInput.max = pendingAmount;

    document.getElementById('modal_cash_received').value = pendingAmount;
    calculateChange();

    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

document.getElementById('btnPayFull').addEventListener('click', function() {
    const amountInput = document.getElementById('modal_amount');
    amountInput.value = currentPendingAmount;
    document.getElementById('modal_cash_received').value = currentPendingAmount;
    calculateChange();
});

document.getElementById('modal_amount').addEventListener('input', function() {
    if (parseFloat(this.value) > currentPendingAmount) {
        this.value = currentPendingAmount;
    }
    calculateChange();
});

function togglePaymentFields() {
    const isCash = document.getElementById('pay_cash').checked;
    document.getElementById('cashFields').style.display = isCash ? 'flex' : 'none';
    if (!isCash) {
        document.getElementById('modal_cash_received').value = '';
        document.getElementById('modal_change_display').value = '$0';
    } else {
        document.getElementById('modal_cash_received').value = document.getElementById('modal_amount').value;
        calculateChange();
    }
}

function calculateChange() {
    const amount = parseFloat(document.getElementById('modal_amount').value) || 0;
    const received = parseFloat(document.getElementById('modal_cash_received').value) || 0;
    const change = Math.max(0, received - amount);
    document.getElementById('modal_change_display').value = '$' + new Intl.NumberFormat('es-CO').format(change);
}

function switchCreditTab(tab) {
    const groupedTab = document.getElementById('grouped-credit-tab');
    const invoicesTab = document.getElementById('invoices-credit-tab');
    const groupedPane = document.getElementById('grouped-credit-pane');
    const invoicesPane = document.getElementById('invoices-credit-pane');
    const activeTabInput = document.getElementById('activeCreditTabInput');
    const groupedBadge = document.getElementById('grouped-count-badge');
    const invoicesBadge = document.getElementById('invoices-count-badge');

    if (tab === 'grouped') {
        if (groupedTab) {
            groupedTab.style.backgroundColor = '#10B981';
            groupedTab.style.color = '#FFFFFF';
            groupedTab.classList.add('active-tab', 'text-white', 'shadow-sm');
            groupedTab.classList.remove('inactive-tab', 'text-secondary');
        }
        if (invoicesTab) {
            invoicesTab.style.backgroundColor = 'transparent';
            invoicesTab.style.color = '#4B5563';
            invoicesTab.classList.remove('active-tab', 'text-white', 'shadow-sm');
            invoicesTab.classList.add('inactive-tab', 'text-secondary');
        }
        if (groupedBadge) {
            groupedBadge.style.backgroundColor = 'rgba(255,255,255,0.25)';
            groupedBadge.style.color = '#FFFFFF';
        }
        if (invoicesBadge) {
            invoicesBadge.style.backgroundColor = '#6B7280';
            invoicesBadge.style.color = '#FFFFFF';
        }
        if (groupedPane) groupedPane.classList.add('show', 'active');
        if (invoicesPane) invoicesPane.classList.remove('show', 'active');
        if (activeTabInput) activeTabInput.value = 'grouped';
    } else {
        if (invoicesTab) {
            invoicesTab.style.backgroundColor = '#10B981';
            invoicesTab.style.color = '#FFFFFF';
            invoicesTab.classList.add('active-tab', 'text-white', 'shadow-sm');
            invoicesTab.classList.remove('inactive-tab', 'text-secondary');
        }
        if (groupedTab) {
            groupedTab.style.backgroundColor = 'transparent';
            groupedTab.style.color = '#4B5563';
            groupedTab.classList.remove('active-tab', 'text-white', 'shadow-sm');
            groupedTab.classList.add('inactive-tab', 'text-secondary');
        }
        if (invoicesBadge) {
            invoicesBadge.style.backgroundColor = 'rgba(255,255,255,0.25)';
            invoicesBadge.style.color = '#FFFFFF';
        }
        if (groupedBadge) {
            groupedBadge.style.backgroundColor = '#10B981';
            groupedBadge.style.color = '#FFFFFF';
        }
        if (invoicesPane) invoicesPane.classList.add('show', 'active');
        if (groupedPane) groupedPane.classList.remove('show', 'active');
        if (activeTabInput) activeTabInput.value = 'invoices';
    }

    try {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    } catch (e) {}
}
</script>
@endsection
