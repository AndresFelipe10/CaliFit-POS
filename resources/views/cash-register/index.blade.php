@extends('layouts.app')

@section('title', 'Cuadre de Caja Diario y Arqueo | SaborCaliFit')

@section('content')
<div class="container-fluid px-2 px-md-3 py-3">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">
                <i class="bi bi-cash-coin text-success me-2"></i>Cuadre de Caja Diario y Arqueo
            </h1>
            <p class="text-muted small mb-0">Consolidación financiera, discriminación por canales y arqueo físico vs digital (SaborCaliFit Bochalema).</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            {{-- Filtro de Fecha --}}
            <form method="GET" action="{{ route('cash-register.index') }}" class="d-flex gap-1 align-items-center">
                <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}" onchange="this.form.submit()">
                <a href="{{ route('cash-register.index', ['date' => now()->timezone('America/Bogota')->format('Y-m-d')]) }}" class="btn btn-outline-secondary btn-sm" title="Ver Hoy">
                    Hoy
                </a>
            </form>
            {{-- Botón de Impresión Térmica --}}
            <button type="button" class="btn btn-califit btn-sm px-3 py-2 fw-bold shadow-sm" onclick="openPrintWindow('{{ route('prints.cash-register', ['date' => $date]) }}')">
                <i class="bi bi-printer me-1"></i> Imprimir Cuadre Térmico (80mm)
            </button>
        </div>
    </div>

    {{-- Fecha Consultada Badge --}}
    <div class="alert alert-light border shadow-sm p-2 mb-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="text-muted small">Mostrando operaciones del:</span>
            <strong class="text-dark ms-1 fs-6">{{ \Carbon\Carbon::parse($date)->translatedFormat('l, d \d\e F \d\e Y') }}</strong>
        </div>
        <div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                <i class="bi bi-check-circle me-1"></i>{{ $salesCount }} ventas completadas
            </span>
        </div>
    </div>

    {{-- Cuadrícula de Conciliación Principal --}}
    <div class="row g-3 mb-4">
        {{-- Tarjeta 1: Resumen General de Facturación --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-receipt text-success me-2"></i>Resumen de Facturación
                    </h6>
                    <small class="text-muted">Totales facturados brutos e impuestos</small>
                </div>
                <div class="card-body">
                    <div class="bg-light p-3 rounded-3 mb-3 text-center border">
                        <small class="text-muted fw-semibold d-block text-uppercase" style="font-size: 0.75rem;">Total Facturado Neto</small>
                        <h2 class="fw-bold text-success mb-0">${{ number_format($netTotalBilled, 0, ',', '.') }}</h2>
                        <small class="text-muted">{{ $salesCount }} transacciones cerradas</small>
                    </div>

                    <div class="d-flex justify-content-between py-1 border-bottom small">
                        <span class="text-muted">Ventas Brutas (Subtotal):</span>
                        <span class="font-monospace fw-semibold">${{ number_format($grossSales, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom small">
                        <span class="text-muted">Descuentos Aplicados:</span>
                        <span class="font-monospace text-danger fw-semibold">-${{ number_format($discounts, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom small bg-light px-1">
                        <span class="fw-bold text-dark">Ventas Netas de Productos:</span>
                        <span class="font-monospace fw-bold text-dark">${{ number_format(max(0, $grossSales - $discounts), 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom small">
                        <span class="text-muted"><i class="bi bi-heart text-success me-1"></i>Propinas Recaudadas:</span>
                        <span class="font-monospace text-success fw-bold">+${{ number_format($serviceTips, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom small">
                        <span class="text-muted">Impuesto al Consumo (INC 8%):</span>
                        <span class="font-monospace fw-semibold">${{ number_format($taxInc, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 small">
                        <span class="text-muted">Fletes Totales Domicilio:</span>
                        <span class="font-monospace fw-semibold text-warning-emphasis">${{ number_format($deliveryFees, 0, ',', '.') }}</span>
                    </div>

                    {{-- Fila Informativa de Créditos Otorgados Hoy --}}
                    <div class="p-2 rounded bg-warning bg-opacity-10 border border-warning border-opacity-25 mt-2">
                        <div class="d-flex justify-content-between align-items-center small">
                            <span class="text-warning-emphasis fw-bold">
                                <i class="bi bi-clock-history me-1"></i> Créditos Otorgados Hoy:
                            </span>
                            <span class="font-monospace fw-bold text-warning-emphasis">${{ number_format($creditSalesTotal, 0, ',', '.') }}</span>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">
                            {{ $creditSalesCount }} ventas fiadas (descontadas de stock; cobro pendiente).
                        </small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tarjeta 2: Desglose por Canal de Venta --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>Canales de Venta
                    </h6>
                    <small class="text-muted">Facturación discriminada por punto de consumo</small>
                </div>
                <div class="card-body">
                    {{-- Salón --}}
                    <div class="p-2 mb-2 rounded border-start border-4 border-success bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-dark d-block"><i class="bi bi-shop me-1 text-success"></i> Salón (Mesas)</span>
                                <small class="text-muted">{{ $tableSales->count() }} pedidos de comensales</small>
                            </div>
                            <span class="h6 font-monospace fw-bold mb-0 text-dark">${{ number_format($tableTotal, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Domicilios --}}
                    <div class="p-2 mb-2 rounded border-start border-4 border-warning bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-dark d-block"><i class="bi bi-bicycle me-1 text-warning"></i> Domicilios</span>
                                <small class="text-muted">{{ $deliverySales->count() }} despachos</small>
                            </div>
                            <span class="h6 font-monospace fw-bold mb-0 text-dark">${{ number_format($deliveryTotal, 0, ',', '.') }}</span>
                        </div>
                        {{-- Desglose Fletes repartidores --}}
                        <div class="mt-2 pt-1 border-top d-flex justify-content-between align-items-center" style="font-size: 0.78rem;">
                            <span class="text-muted"><i class="bi bi-arrow-return-right me-1"></i>Fletes Recaudados para Repartidores:</span>
                            <strong class="font-monospace text-warning-emphasis">${{ number_format($deliveryFeesTotal, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    {{-- Para Llevar --}}
                    <div class="p-2 mb-2 rounded border-start border-4 border-info bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-dark d-block"><i class="bi bi-bag-check me-1 text-info"></i> Para Llevar</span>
                                <small class="text-muted">{{ $takeoutSales->count() }} pedidos empaquetados</small>
                            </div>
                            <span class="h6 font-monospace fw-bold mb-0 text-dark">${{ number_format($takeoutTotal, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Mostrador / Suplementos --}}
                    <div class="p-2 rounded border-start border-4 border-secondary bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-dark d-block"><i class="bi bi-lightning-charge me-1 text-secondary"></i> Mostrador / Retail</span>
                                <small class="text-muted">{{ $retailSales->count() }} suplementos / directo</small>
                            </div>
                            <span class="h6 font-monospace fw-bold mb-0 text-dark">${{ number_format($retailTotal, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tarjeta 3: Arqueo y Conciliación de Medios de Pago --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-0 pt-3 pb-0">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-wallet2 text-success me-2"></i>Arqueo de Medios de Pago
                    </h6>
                    <small class="text-muted">Control de gaveta física vs dinero digital</small>
                </div>
                <div class="card-body">
                    {{-- Fila Efectivo Físico en Gaveta (Destacada) --}}
                    <div class="p-3 mb-3 rounded-3 border border-success bg-success-subtle">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-success-emphasis">
                                <i class="bi bi-cash-stack me-1"></i> Efectivo Neto en Gaveta
                            </span>
                            <span class="h5 font-monospace fw-bold text-success-emphasis mb-0">
                                ${{ number_format($netCashInDrawer, 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between text-muted border-top border-success-subtle pt-1 mt-1" style="font-size: 0.75rem;">
                            <span>Ingresos Efectivo (Ventas + Cartera):</span>
                            <span class="fw-semibold text-dark">+${{ number_format($grossCashInDrawer, 0, ',', '.') }}</span>
                        </div>
                        @if($expensesCashTotal > 0)
                            <div class="d-flex justify-content-between text-danger pt-1" style="font-size: 0.75rem;">
                                <span>Salidas por Gastos en Efectivo:</span>
                                <span class="fw-semibold">-${{ number_format($expensesCashTotal, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between text-muted pt-1 border-top border-dashed" style="font-size: 0.72rem;">
                            <span>Recibido Bruto: ${{ number_format($cashReceived, 0, ',', '.') }}</span>
                            <span>Vueltos Entregados: -${{ number_format($changeGiven, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Transferencias --}}
                    <div class="d-flex justify-content-between align-items-center p-2 mb-2 rounded bg-light border-start border-4 border-primary">
                        <div>
                            <span class="fw-bold text-dark small d-block"><i class="bi bi-phone me-1 text-primary"></i> Transferencias (Nequi / Daviplata / Bancos)</span>
                            <small class="text-muted">Dinero en cuenta digital</small>
                        </div>
                        <span class="font-monospace fw-bold text-dark">${{ number_format($transferAmount, 0, ',', '.') }}</span>
                    </div>

                    {{-- Tarjetas / Datáfono --}}
                    <div class="d-flex justify-content-between align-items-center p-2 mb-2 rounded bg-light border-start border-4 border-info">
                        <div>
                            <span class="fw-bold text-dark small d-block"><i class="bi bi-credit-card me-1 text-info"></i> Tarjetas (Datáfono / POS)</span>
                            <small class="text-muted">Vouchers electrónicos</small>
                        </div>
                        <span class="font-monospace fw-bold text-dark">${{ number_format($cardAmount, 0, ',', '.') }}</span>
                    </div>

                    {{-- Otros Métodos --}}
                    @if($otherAmount > 0)
                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 rounded bg-light border-start border-4 border-secondary">
                            <div>
                                <span class="fw-bold text-dark small d-block"><i class="bi bi-three-dots me-1 text-secondary"></i> Otros Métodos</span>
                            </div>
                            <span class="font-monospace fw-bold text-dark">${{ number_format($otherAmount, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    {{-- Recaudos de Cartera del Día (Suma Efectiva a Caja) --}}
                    <div class="p-2 mb-2 rounded bg-success-subtle border-start border-4 border-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-success-emphasis small d-block"><i class="bi bi-cash-coin me-1"></i> Recaudos de Cartera / Cobro de Créditos:</span>
                                <small class="text-muted">{{ $creditCollectionsCount }} abonos recibidos hoy</small>
                            </div>
                            <span class="font-monospace fw-bold text-success-emphasis">+${{ number_format($creditCollectionsTotal, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Resumen de Egresos / Gastos Pagados Hoy --}}
                    <div class="p-2 mb-2 rounded bg-danger bg-opacity-10 border-start border-4 border-danger">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-danger small d-block"><i class="bi bi-arrow-up-right-circle me-1"></i> Egresos / Gastos Pagados Hoy:</span>
                                <small class="text-muted" style="font-size: 0.72rem;">
                                    Efectivo: -${{ number_format($expensesCashTotal, 0, ',', '.') }} | Transf./Tarj: -${{ number_format($expensesTransferTotal + $expensesCardOtherTotal, 0, ',', '.') }}
                                </small>
                            </div>
                            <span class="font-monospace fw-bold text-danger">-${{ number_format($totalExpensesPaidToday, 0, ',', '.') }}</span>
                        </div>
                        @if($totalExpensesPendingToday > 0)
                            <div class="mt-1 pt-1 border-top border-danger border-opacity-25 small text-warning-emphasis" style="font-size: 0.72rem;">
                                <i class="bi bi-clock-history me-1"></i> Facturas pendientes registradas hoy: <strong>${{ number_format($totalExpensesPendingToday, 0, ',', '.') }}</strong>
                            </div>
                        @endif
                    </div>

                    {{-- Total Recaudado y Balance Neto --}}
                    <div class="mt-3 pt-2 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div>
                                <span class="text-muted small d-block">Total Bruto Recaudado</span>
                                <small class="text-muted" style="font-size: 0.7rem;">Ventas + Cartera</small>
                            </div>
                            <span class="font-monospace fw-semibold text-dark">${{ number_format($totalCollected, 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <div>
                                <strong class="text-dark d-block">Balance Neto en Caja</strong>
                                <small class="text-muted" style="font-size: 0.7rem;">Recaudado - Gastos Pagados</small>
                            </div>
                            <span class="h5 font-monospace fw-bold {{ $netCashBalance >= 0 ? 'text-success' : 'text-danger' }} mb-0">
                                ${{ number_format($netCashBalance, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla Detalle de Transacciones del Día --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-list-check text-secondary me-2"></i>Detalle de Ventas del Turno / Día ({{ $salesCount }})
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3" style="width: 110px;">Folio</th>
                        <th style="width: 90px;">Hora</th>
                        <th style="width: 140px;">Canal</th>
                        <th>Cliente / Mesa</th>
                        <th style="width: 140px;">Cajero / Mesero</th>
                        <th class="text-center" style="width: 150px;">Medio(s) de Pago</th>
                        <th class="text-end" style="width: 120px;">Flete</th>
                        <th class="text-end pe-3" style="width: 140px;">Total</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse($sales as $sale)
                        <tr>
                            <td class="ps-3 font-monospace fw-bold text-dark">
                                #{{ $sale->invoice_number ?? str_pad($sale->id, 5, '0', STR_PAD_LEFT) }}
                            </td>
                            <td class="text-muted">
                                {{ $sale->created_at->timezone('America/Bogota')->format('H:i') }}
                            </td>
                            <td>
                                @php
                                    $channelBadge = match($sale->order_type) {
                                        'table' => 'bg-success text-white',
                                        'delivery' => 'bg-warning text-dark',
                                        'takeout' => 'bg-info text-dark',
                                        default => 'bg-secondary text-white',
                                    };
                                    $channelName = match($sale->order_type) {
                                        'table' => 'Salón (Mesa)',
                                        'delivery' => 'Domicilio',
                                        'takeout' => 'Para Llevar',
                                        default => 'Mostrador',
                                    };
                                @endphp
                                <span class="badge {{ $channelBadge }} px-2 py-1 rounded-pill">
                                    {{ $channelName }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark">{{ $sale->customer_name ?? 'Cliente Ocasional' }}</span>
                                @if($sale->table_id)
                                    <span class="badge bg-light text-dark border ms-1">Mesa {{ $sale->table_id }}</span>
                                @endif
                            </td>
                            <td class="text-muted">
                                <i class="bi bi-person me-1"></i>{{ $sale->user?->name ?? 'Cajero' }}
                            </td>
                            <td class="text-center">
                                @if($sale->is_credit)
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1" style="font-size: 0.72rem;">
                                        <i class="bi bi-wallet2 me-1"></i>Crédito / Fiado: ${{ number_format($sale->total, 0, ',', '.') }}
                                    </span>
                                @else
                                    @forelse($sale->payments as $payment)
                                        @php
                                            $mVal = $payment->method instanceof \BackedEnum ? $payment->method->value : (string) $payment->method;
                                            $mVal = strtolower((string)$mVal);
                                            $methodBadge = match($mVal) {
                                                'cash' => 'bg-success-subtle text-success border border-success',
                                                'nequi', 'daviplata', 'transfer' => 'bg-primary-subtle text-primary border border-primary',
                                                'card' => 'bg-info-subtle text-info border border-info',
                                                'credit' => 'bg-warning-subtle text-warning-emphasis border border-warning',
                                                default => 'bg-light text-dark border',
                                            };
                                            $methodName = match($mVal) {
                                                'cash' => 'Efectivo',
                                                'nequi' => 'Nequi',
                                                'daviplata' => 'Daviplata',
                                                'transfer' => 'Transferencia',
                                                'card' => 'Tarjeta',
                                                'credit' => 'Crédito / Fiado',
                                                default => ucfirst($mVal),
                                            };
                                        @endphp
                                        <span class="badge {{ $methodBadge }} px-1 py-0" style="font-size: 0.7rem;">
                                            {{ $methodName }}: ${{ number_format($payment->amount, 0, ',', '.') }}
                                        </span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                @endif
                            </td>
                            <td class="text-end font-monospace text-muted">
                                @if((float)$sale->delivery_fee > 0)
                                    <span class="text-warning-emphasis fw-semibold">${{ number_format($sale->delivery_fee, 0, ',', '.') }}</span>
                                @else
                                    $0
                                @endif
                            </td>
                            <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                ${{ number_format($sale->total, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                No se registraron ventas en la fecha seleccionada ({{ $date }}).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tabla de Gastos y Facturas del Día (Trazabilidad Contable Integral) --}}
    <div class="card border-0 shadow-sm rounded-3 mt-4">
        <div class="card-header bg-white border-0 pt-3 pb-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-receipt-cutoff text-danger me-2"></i>Detalle de Gastos y Facturas Registrados en la Fecha ({{ $expensesToday->count() }})
                </h6>
                <small class="text-muted">Egresos pagados en caja y cuentas por pagar registradas en el turno</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                    Egresos Pagados: -${{ number_format($totalExpensesPaidToday, 0, ',', '.') }}
                </span>
                <a href="{{ route('expenses.index') }}" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Ir a Gastos y Facturas
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3" style="width: 140px;">Factura / Ref #</th>
                        <th>Proveedor / Descripción</th>
                        <th style="width: 180px;">Categoría</th>
                        <th style="width: 150px;">Registrado por</th>
                        <th class="text-center" style="width: 160px;">Estado</th>
                        <th class="text-center" style="width: 130px;">Medio de Pago</th>
                        <th class="text-end pe-3" style="width: 140px;">Monto</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse($expensesToday as $expense)
                        @php
                            $catLabel = match($expense->category instanceof \BackedEnum ? $expense->category->value : (string) $expense->category) {
                                'merchandise' => 'Mercancía / Inventario',
                                'utilities' => 'Servicios públicos',
                                'payroll' => 'Nómina',
                                'rent' => 'Arriendo',
                                'supplies' => 'Mantenimiento e Insumos',
                                default => 'Otros',
                            };
                            $expMethodVal = $expense->payment_method instanceof \BackedEnum ? $expense->payment_method->value : (string) $expense->payment_method;
                            $expMethodVal = strtolower((string)$expMethodVal);
                            $expMethod = match($expMethodVal) {
                                'cash' => 'Efectivo',
                                'nequi' => 'Nequi',
                                'daviplata' => 'Daviplata',
                                'transfer' => 'Transferencia',
                                'card' => 'Tarjeta',
                                'other' => 'Otro',
                                default => ($expense->status === 'paid' ? (ucfirst($expMethodVal) ?: 'No especificado') : '-'),
                            };
                        @endphp
                        <tr>
                            <td class="ps-3 font-monospace fw-bold text-dark">
                                {{ $expense->invoice_number ?: 'S/N' }}
                            </td>
                            <td>
                                @if($expense->supplier)
                                    <strong class="text-dark d-block">{{ $expense->supplier->name }}</strong>
                                @endif
                                <span class="text-muted">{{ $expense->description ?: 'Sin descripción' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border">
                                    {{ $catLabel }}
                                </span>
                            </td>
                            <td class="text-muted">
                                <i class="bi bi-person me-1"></i>{{ $expense->creator?->name ?? $expense->user?->name ?? 'Admin' }}
                            </td>
                            <td class="text-center">
                                @if($expense->status === 'paid')
                                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i>Pagado
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1 rounded-pill">
                                        <i class="bi bi-clock-history me-1"></i>Pendiente por Pagar
                                    </span>
                                @endif
                            </td>
                            <td class="text-center font-monospace">
                                <span class="badge bg-light text-dark border">
                                    {{ $expMethod }}
                                </span>
                            </td>
                            <td class="text-end pe-3 font-monospace fw-bold {{ $expense->status === 'paid' ? 'text-danger' : 'text-warning-emphasis' }}">
                                {{ $expense->status === 'paid' ? '-' : '' }}${{ number_format($expense->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary"></i>
                                No se registraron gastos ni facturas en esta fecha.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tabla de Recaudos de Cartera / Cobros de Crédito del Día --}}
    @if($creditCollectionsCount > 0)
        <div class="card border-0 shadow-sm rounded-3 mt-4">
            <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-success-emphasis mb-0">
                    <i class="bi bi-cash-coin text-success me-2"></i>Recaudos de Cartera / Abonos Recibidos Hoy ({{ $creditCollectionsCount }})
                </h6>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    Total Recaudado de Cartera: ${{ number_format($creditCollectionsTotal, 0, ',', '.') }}
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th class="ps-3">Hora</th>
                            <th>Factura Afectada</th>
                            <th>Cliente</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th>Cajero / Responsable</th>
                            <th class="text-end pe-3">Monto Abonado</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @foreach($creditCollections as $col)
                            <tr>
                                <td class="ps-3 font-monospace text-muted">{{ $col->paid_at->timezone('America/Bogota')->format('H:i:s') }}</td>
                                <td>
                                    <span class="font-monospace fw-bold text-dark">{{ $col->sale->invoice_number ?? 'N/A' }}</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $col->customer->name ?? 'Cliente' }}</span>
                                    <small class="text-muted d-block font-monospace">{{ $col->customer->document ?? '' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        {{ $col->method_label }}
                                    </span>
                                </td>
                                <td class="text-muted font-monospace">{{ $col->reference ?: '-' }}</td>
                                <td>{{ $col->user->name ?? 'Cajero' }}</td>
                                <td class="text-end pe-3 font-monospace fw-bold text-success">
                                    +${{ number_format($col->amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- Script para abrir ventana emergente de impresión (80mm) --}}
<script>
function openPrintWindow(url) {
    const width = 450;
    const height = 680;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    window.open(url, 'TicketCuadreCaliFit', `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,status=no,toolbar=no`);
}
</script>
@endsection
