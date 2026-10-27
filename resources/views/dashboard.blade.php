@extends('layouts.app')

@section('title', 'Dashboard | SaborCaliFit POS')

@section('content')
<div class="container-fluid px-2 px-md-3 py-3">
    {{-- Banner de Bienvenida --}}
    <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3 bg-white">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h2 class="h4 fw-bold text-dark mb-0">¡Hola, {{ $user->name }}!</h2>
                    @if($isAdmin)
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Admin / Gerencia</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">Operaciones POS</span>
                    @endif
                </div>
                <p class="text-muted small mb-0 mt-1">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i>SABORCALIFIT | BOCHALEMA &bull; Arbolatta Mall, Cali, Colombia
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="badge bg-light text-muted border px-3 py-2 fs-6">
                    <i class="bi bi-calendar3 me-1 text-success"></i>{{ now()->timezone('America/Bogota')->translatedFormat('d \d\e F \d\e Y') }}
                </span>
                <a href="{{ route('pos.index') }}" class="btn btn-califit btn-sm px-3 py-2 fw-bold shadow-sm">
                    <i class="bi bi-calculator me-1"></i> Ir al POS
                </a>
            </div>
        </div>
    </div>

    @if($isAdmin)
        {{-- ======================================================== --}}
        {{-- PANEL GERENCIAL / ADMINISTRATIVO (MÉTRICAS FINANCIERAS)  --}}
        {{-- ======================================================== --}}

        {{-- Selector Ergonómico de Período (Día vs Mes) --}}
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-3 bg-white">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 gap-sm-3">
                <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center gap-2 gap-sm-3 w-100 w-sm-auto">
                    <div class="d-flex align-items-center justify-content-between w-100 w-sm-auto">
                        <span class="small fw-bold text-muted text-uppercase d-flex align-items-center" style="font-size: 0.75rem; letter-spacing: 0.5px; white-space: nowrap;">
                            <i class="bi bi-funnel-fill text-success me-1"></i>Período Financiero:
                        </span>
                        <span class="badge bg-light text-dark border d-inline-block d-sm-none small fw-semibold">
                            {{ $periodLabel ?? 'Mes en curso' }}
                        </span>
                    </div>
                    <div class="btn-group btn-group-sm w-100 w-sm-auto shadow-xs" role="group" aria-label="Alternar período">
                        <a href="{{ route('dashboard', ['period' => 'day', 'date' => $selectedDate ?? now()->timezone('America/Bogota')->toDateString()]) }}" 
                           class="btn {{ ($period ?? 'month') === 'day' ? 'btn-success fw-bold text-white' : 'btn-outline-secondary' }} d-inline-flex align-items-center justify-content-center" 
                           style="{{ ($period ?? 'month') === 'day' ? 'background-color: #10B981; border-color: #10B981;' : '' }} min-height: 40px; padding: 0.5rem 0.85rem;">
                            <i class="bi bi-calendar-event me-1"></i> Hoy (Día)
                        </a>
                        <a href="{{ route('dashboard', ['period' => 'month', 'month' => $selectedMonth ?? now()->timezone('America/Bogota')->format('Y-m')]) }}" 
                           class="btn {{ ($period ?? 'month') === 'month' ? 'btn-success fw-bold text-white' : 'btn-outline-secondary' }} d-inline-flex align-items-center justify-content-center"
                           style="{{ ($period ?? 'month') === 'month' ? 'background-color: #10B981; border-color: #10B981;' : '' }} min-height: 40px; padding: 0.5rem 0.85rem;">
                            <i class="bi bi-calendar3 me-1"></i> Mes Actual
                        </a>
                    </div>
                    <span class="badge bg-light text-dark border d-none d-sm-inline-block small fw-semibold py-2 px-2.5">
                        {{ $periodLabel ?? 'Mes en curso' }}
                    </span>
                </div>

                <form action="{{ route('dashboard') }}" method="GET" class="w-100 w-sm-auto d-flex align-items-center gap-2">
                    <input type="hidden" name="period" value="{{ $period ?? 'month' }}">
                    @if(($period ?? 'month') === 'day')
                        <div class="input-group input-group-sm w-100">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar-date text-muted"></i></span>
                            <input type="date" name="date" class="form-control form-control-sm border-start-0" style="min-height: 40px;" value="{{ $selectedDate ?? now()->timezone('America/Bogota')->toDateString() }}" onchange="this.form.submit()">
                        </div>
                    @else
                        <div class="input-group input-group-sm w-100">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar-month text-muted"></i></span>
                            <input type="month" name="month" class="form-control form-control-sm border-start-0" style="min-height: 40px;" value="{{ $selectedMonth ?? now()->timezone('America/Bogota')->format('Y-m') }}" onchange="this.form.submit()">
                        </div>
                    @endif
                </form>
            </div>
        </div>

        {{-- Fila de KPIs Financieros Superiores --}}
        <div class="row g-2 g-md-3 mb-3">
            {{-- 1. Ventas --}}
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 h-100 border-start border-4 border-success">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-semibold text-uppercase" style="font-size: 0.72rem;">
                            {{ ($period ?? 'month') === 'day' ? 'Ventas del Día' : 'Ventas del Mes' }}
                        </small>
                        <i class="bi bi-graph-up text-success fs-5"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-0">${{ number_format($periodSalesTotal ?? $monthSalesTotal, 0, ',', '.') }}</h4>
                    <small class="text-muted" style="font-size: 0.72rem;">{{ $periodSalesCount ?? $monthSalesCount }} transacciones cerradas</small>
                </div>
            </div>

            {{-- 2. Gastos --}}
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 h-100 border-start border-4 border-danger">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-semibold text-uppercase" style="font-size: 0.72rem;">
                            {{ ($period ?? 'month') === 'day' ? 'Gastos del Día' : 'Gastos del Mes' }}
                        </small>
                        <i class="bi bi-receipt-cutoff text-danger fs-5"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-0">${{ number_format($periodExpensesTotal ?? $monthExpensesTotal, 0, ',', '.') }}</h4>
                    <small class="text-muted" style="font-size: 0.72rem;">Pendientes: ${{ number_format($pendingExpensesTotal, 0, ',', '.') }}</small>
                </div>
            </div>

            {{-- 3. Utilidad Neta Estimada (Ventas - Gastos) --}}
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 h-100 border-start border-4 {{ $netProfit >= 0 ? 'border-primary' : 'border-danger' }}">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-semibold text-uppercase" style="font-size: 0.72rem;">
                            {{ ($period ?? 'month') === 'day' ? 'Utilidad Neta Día' : 'Utilidad Neta Mes' }}
                        </small>
                        <i class="bi bi-wallet2 {{ $netProfit >= 0 ? 'text-primary' : 'text-danger' }} fs-5"></i>
                    </div>
                    <h4 class="fw-bold mb-0 {{ $netProfit >= 0 ? 'text-primary' : 'text-danger' }}">
                        ${{ number_format($netProfit, 0, ',', '.') }}
                    </h4>
                    <small class="text-muted" style="font-size: 0.72rem;">Ventas netas menos gastos</small>
                </div>
            </div>

            {{-- 4. Ticket Promedio --}}
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 h-100 border-start border-4 border-info">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-semibold text-uppercase" style="font-size: 0.72rem;">Ticket Promedio</small>
                        <i class="bi bi-tag text-info fs-5"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-0">${{ number_format($averageTicket, 0, ',', '.') }}</h4>
                    <small class="text-muted" style="font-size: 0.72rem;">Por compra realizada</small>
                </div>
            </div>
        </div>

        {{-- Fila de Resumen Diario y Operativo --}}
        <div class="row g-2 g-md-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted fw-semibold d-block">Facturación de Hoy</small>
                            <h4 class="fw-bold text-success mb-0">${{ number_format($todaySalesTotal, 0, ',', '.') }}</h4>
                            <small class="text-muted">{{ $todaySalesCount }} ventas completadas</small>
                        </div>
                        <a href="{{ route('cash-register.index') }}" class="btn btn-outline-success btn-sm px-2 px-sm-3 py-1.5 fw-semibold d-inline-flex align-items-center" style="min-height: 38px;">
                            Ver Cuadre &rarr;
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100 border-start border-4 border-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted fw-semibold d-block">Propinas ({{ ($period ?? 'month') === 'day' ? 'Día' : 'Mes' }})</small>
                            <h4 class="fw-bold text-success mb-0">${{ number_format($periodTipsTotal ?? 0, 0, ',', '.') }}</h4>
                            <small class="text-muted">{{ $periodTipsCount ?? 0 }} ventas con propina</small>
                        </div>
                        <div class="p-2 bg-success-subtle text-success rounded-circle">
                            <i class="bi bi-heart-fill fs-5"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted fw-semibold d-block">Salón: Mesas Ocupadas</small>
                            <h4 class="fw-bold text-warning-emphasis mb-0">{{ $occupiedTablesCount }} mesas</h4>
                            <small class="text-muted">Comensales en servicio</small>
                        </div>
                        <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-secondary btn-sm px-2 px-sm-3 py-1.5 fw-semibold d-inline-flex align-items-center" style="min-height: 38px;">
                            Ver Salón &rarr;
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted fw-semibold d-block">Despacho Activo</small>
                            <h4 class="fw-bold text-info mb-0">{{ $activeOrdersCount }} pedidos</h4>
                            <small class="text-muted">Domicilios y Para Llevar</small>
                        </div>
                        <a href="{{ route('restaurant.dispatch.index') }}" class="btn btn-outline-secondary btn-sm px-2 px-sm-3 py-1.5 fw-semibold d-inline-flex align-items-center" style="min-height: 38px;">
                            Ver Despacho &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Fila Inferior: Top 5 Productos y Alertas de Stock --}}
        <div class="row g-2 g-md-3 mb-3">
            {{-- Top 5 Productos --}}
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-trophy text-warning me-2"></i>Top 5 Productos Más Vendidos ({{ ($period ?? 'month') === 'day' ? 'Día' : 'Mes' }})
                        </h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Producto</th>
                                    <th class="text-center" style="width: 100px;">Cantidad</th>
                                    <th class="text-end pe-3" style="width: 130px;">Total Ventas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topProducts as $item)
                                    <tr>
                                        <td class="ps-3 fw-semibold text-dark">
                                            {{ $item->product?->nombre ?? 'Producto #' . $item->product_id }}
                                        </td>
                                        <td class="text-center font-monospace fw-bold text-success">
                                            {{ number_format($item->total_qty, 0) }}
                                        </td>
                                        <td class="text-end pe-3 font-monospace text-dark">
                                            ${{ number_format($item->total_amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            No hay registros de ventas para este mes aún.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Alertas de Stock Crítico --}}
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-exclamation-triangle text-danger me-2"></i>Alertas de Reposición Inmediata
                        </h6>
                        <a href="{{ route('inventory.index') }}" class="btn btn-outline-success btn-sm" style="font-size: 0.75rem;">
                            Gestionar Stock &rarr;
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Insumo / Suplemento</th>
                                    <th class="text-end" style="width: 110px;">Stock</th>
                                    <th class="text-end" style="width: 100px;">Mínimo</th>
                                    <th class="text-center pe-3" style="width: 110px;">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($criticalStockProducts as $prod)
                                    @php
                                        $isOut = (float) $prod->stock <= 0;
                                    @endphp
                                    <tr>
                                        <td class="ps-3">
                                            <span class="fw-semibold text-dark">{{ $prod->nombre }}</span>
                                            <span class="badge bg-light text-muted border ms-1" style="font-size: 0.68rem;">{{ $prod->tipo_producto }}</span>
                                        </td>
                                        <td class="text-end font-monospace fw-bold {{ $isOut ? 'text-danger' : 'text-warning-emphasis' }}">
                                            {{ number_format($prod->stock, 1) }} <span class="small">{{ $prod->unidad_base }}</span>
                                        </td>
                                        <td class="text-end font-monospace text-muted">
                                            {{ number_format($prod->stock_minimo, 1) }}
                                        </td>
                                        <td class="text-center pe-3">
                                            @if($isOut)
                                                <span class="badge bg-danger text-white rounded-pill px-2 py-1">Agotado</span>
                                            @else
                                                <span class="badge bg-warning text-dark rounded-pill px-2 py-1">Bajo Stock</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="bi bi-check-circle text-success fs-5 d-block mb-1"></i>
                                            Todos los insumos y suplementos tienen stock adecuado.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    @else
        {{-- ======================================================== --}}
        {{-- PANEL OPERATIVO PARA EMPLEADOS (CERO DINERO VISIBLE)     --}}
        {{-- ======================================================== --}}

        <div class="row g-2 g-md-3 mb-4">
            {{-- 1. Ventas realizadas hoy (conteo numérico) --}}
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm p-4 rounded-3 text-center border-start border-4 border-success bg-white h-100">
                    <i class="bi bi-bag-check-fill text-success fs-2 mb-2"></i>
                    <small class="text-muted fw-semibold text-uppercase d-block">Ventas Cerradas Hoy</small>
                    <h2 class="fw-bold text-dark my-2">{{ $todaySalesCount }}</h2>
                    <small class="text-muted">Transacciones completadas en turno</small>
                </div>
            </div>

            {{-- 2. Mesas ocupadas en salón --}}
            <div class="col-6 col-md-4">
                <div class="card border-0 shadow-sm p-4 rounded-3 text-center border-start border-4 border-warning bg-white h-100">
                    <i class="bi bi-grid-3x3-gap-fill text-warning fs-2 mb-2"></i>
                    <small class="text-muted fw-semibold text-uppercase d-block">Mesas Ocupadas</small>
                    <h2 class="fw-bold text-dark my-2">{{ $occupiedTablesCount }}</h2>
                    <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-warning btn-sm fw-semibold">
                        Ver Salón &rarr;
                    </a>
                </div>
            </div>

            {{-- 3. Despacho activo --}}
            <div class="col-6 col-md-4">
                <div class="card border-0 shadow-sm p-4 rounded-3 text-center border-start border-4 border-info bg-white h-100">
                    <i class="bi bi-bicycle text-info fs-2 mb-2"></i>
                    <small class="text-muted fw-semibold text-uppercase d-block">Pedidos en Despacho</small>
                    <h2 class="fw-bold text-dark my-2">{{ $activeOrdersCount }}</h2>
                    <a href="{{ route('restaurant.dispatch.index') }}" class="btn btn-outline-info btn-sm fw-semibold">
                        Ver Despacho &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- Existencias Críticas Físicas para Cocina / Barra (Sin Precios) --}}
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-boxes text-danger me-2"></i>Insumos y Suplementos en Nivel Crítico (Cocina / Barra)
                </h6>
                <small class="text-muted">Avisar oportunamente a administración si algún insumo se agota durante el servicio.</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Producto / Insumo</th>
                            <th class="text-center" style="width: 140px;">Tipo</th>
                            <th class="text-end" style="width: 150px;">Stock Disponible</th>
                            <th class="text-center pe-3" style="width: 130px;">Alerta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($criticalStockProducts as $prod)
                            @php
                                $isOut = (float) $prod->stock <= 0;
                            @endphp
                            <tr>
                                <td class="ps-3 fw-bold text-dark">{{ $prod->nombre }}</td>
                                <td class="text-center">
                                    <span class="badge bg-light text-muted border">{{ $prod->tipo_producto }}</span>
                                </td>
                                <td class="text-end font-monospace fw-bold {{ $isOut ? 'text-danger' : 'text-warning-emphasis' }}">
                                    {{ number_format($prod->stock, 1) }} {{ $prod->unidad_base }}
                                </td>
                                <td class="text-center pe-3">
                                    @if($isOut)
                                        <span class="badge bg-danger text-white rounded-pill px-2 py-1">Agotado</span>
                                    @else
                                        <span class="badge bg-warning text-dark rounded-pill px-2 py-1">Bajo Stock</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="bi bi-check-circle text-success fs-5 d-block mb-1"></i>
                                    Todos los insumos operativos cuentan con existencias suficientes.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
