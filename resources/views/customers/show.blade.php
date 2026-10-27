@extends('layouts.app')

@section('title', 'Ficha y Cartera: ' . $customer->name)

@section('content')
<style>
.back-nav-btn:hover {
    border-color: #10B981 !important;
    transform: translateX(-2px);
    box-shadow: 0 .125rem .25rem rgba(16, 185, 129, 0.15) !important;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <div class="d-flex align-items-center gap-3 mb-3">
            <a href="{{ route('customers.index') }}" class="btn btn-white border rounded-pill px-3 py-2 fw-semibold text-dark shadow-sm d-inline-flex align-items-center gap-2 back-nav-btn" style="transition: all 0.2s ease;">
                <i class="bi bi-arrow-left-circle-fill text-success fs-5"></i>
                <span>Volver al Directorio de Clientes</span>
            </a>
        </div>
        <h3 class="fw-bold mb-0 text-dark">
            Ficha del Cliente y Cartera
        </h3>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('credit.index') }}" class="btn btn-outline-dark rounded-pill px-3">
            <i class="bi bi-wallet2 me-1"></i> Ver Cartera General
        </a>
        <form action="{{ route('customers.toggle-status', $customer) }}" method="POST" class="d-inline">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-arrow-repeat me-1"></i> {{ $customer->is_active ? 'Desactivar' : 'Activar' }}
            </button>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    {{-- Tarjeta de Perfil --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 h-100">
            <div class="text-center mb-3">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white mb-2" style="width: 70px; height: 70px; background-color: #10B981; font-size: 1.8rem;">
                    {{ strtoupper(substr($customer->name, 0, 2)) }}
                </div>
                <h5 class="fw-bold text-dark mb-0">{{ $customer->name }}</h5>
                <span class="badge bg-light text-secondary border font-monospace mt-1">{{ $customer->document ?: 'Sin documento' }}</span>
                <div class="mt-2">
                    @if ($customer->is_active)
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill small">Activo</span>
                    @else
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill small">Inactivo</span>
                    @endif
                </div>
            </div>

            <hr class="my-3">

            <div class="d-flex flex-column gap-2 small">
                <div>
                    <span class="text-muted d-block">Teléfono / WhatsApp:</span>
                    <strong class="text-dark">{{ $customer->phone ?: 'No registrado' }}</strong>
                </div>
                <div>
                    <span class="text-muted d-block">Correo Electrónico:</span>
                    <strong class="text-dark">{{ $customer->email ?: 'No registrado' }}</strong>
                </div>
                <div>
                    <span class="text-muted d-block">Dirección de Despacho:</span>
                    <strong class="text-dark">{{ $customer->address ?: 'No registrada' }}</strong>
                </div>
                <div>
                    <span class="text-muted d-block">Fecha de Registro:</span>
                    <strong class="text-dark">{{ $customer->created_at ? $customer->created_at->format('d/m/Y H:i') : '—' }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Resumen de Consumo y Cartera --}}
    <div class="col-lg-8">
        <div class="row g-3 mb-4">
            {{-- Cartera Pendiente --}}
            <div class="col-sm-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 {{ $customer->credit_balance > 0 ? 'bg-danger bg-opacity-10 border border-danger border-opacity-25' : 'bg-white' }}">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Saldo en Cartera</span>
                            <h3 class="fw-bold mb-0 {{ $customer->credit_balance > 0 ? 'text-danger' : 'text-success' }}">
                                ${{ number_format($customer->credit_balance, 0, ',', '.') }}
                            </h3>
                            <small class="text-muted">{{ $customer->pending_credit_sales_count }} factura(s) pendiente(s)</small>
                        </div>
                        <div class="rounded-circle p-2 {{ $customer->credit_balance > 0 ? 'bg-danger text-white' : 'bg-success bg-opacity-10 text-success' }}">
                            <i class="bi bi-wallet2 fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Facturas Totales --}}
            <div class="col-sm-4">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Total Facturas</span>
                            <h3 class="fw-bold mb-0 text-dark">{{ $customer->sales->count() }}</h3>
                            <small class="text-muted">Historial global</small>
                        </div>
                        <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-receipt fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Volumen Comprado --}}
            <div class="col-sm-4">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Volumen Total</span>
                            <h3 class="fw-bold mb-0 text-success">
                                ${{ number_format($customer->sales->where('status', 'completed')->sum('total'), 0, ',', '.') }}
                            </h3>
                            <small class="text-muted">Facturación acumulada</small>
                        </div>
                        <div class="rounded-circle p-2 bg-success bg-opacity-10 text-success">
                            <i class="bi bi-cash-stack fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pestañas de Historial y Cartera --}}
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div class="card-header bg-white border-bottom p-0">
                <ul class="nav nav-tabs card-header-tabs m-0 px-3 pt-2" id="customerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold text-dark py-3" id="credit-tab" data-bs-toggle="tab" data-bs-target="#credit-pane" type="button" role="tab">
                            <i class="bi bi-clock-history me-1 text-danger"></i> Cuentas por Cobrar / Fiados
                            @if($customer->pending_credit_sales_count > 0)
                                <span class="badge bg-danger rounded-pill ms-1">{{ $customer->pending_credit_sales_count }}</span>
                            @endif
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold text-muted py-3" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales-pane" type="button" role="tab">
                            <i class="bi bi-receipt me-1"></i> Historial General de Ventas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold text-muted py-3" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments-pane" type="button" role="tab">
                            <i class="bi bi-cash-coin me-1 text-success"></i> Abonos y Recaudos Recibidos
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-0">
                <div class="tab-content" id="customerTabsContent">
                    {{-- Pestaña 1: Facturas a Crédito / Fiadas --}}
                    <div class="tab-pane fade show active p-3" id="credit-pane" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-dark">
                                Facturas Otorgadas a Crédito
                            </h6>
                            @if($customer->credit_balance > 0)
                                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="openPaymentModal({{ $customer->creditSales->where('credit_status', 'pending')->first()->id ?? 'null' }}, {{ $customer->creditSales->where('credit_status', 'pending')->first()->credit_pending_amount ?? $customer->credit_balance }}, '{{ $customer->creditSales->where('credit_status', 'pending')->first()->invoice_number ?? 'VARIAS' }}')">
                                    <i class="bi bi-cash me-1"></i> Abonar / Liquidar Deuda
                                </button>
                            @endif
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="small text-uppercase text-muted">
                                        <th class="ps-2">Factura</th>
                                        <th>Fecha Emisión</th>
                                        <th>Vencimiento</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Abonado</th>
                                        <th class="text-end">Saldo Pendiente</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end pe-2">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($customer->creditSales as $cSale)
                                        <tr>
                                            <td class="ps-2">
                                                <span class="font-monospace fw-bold text-dark">{{ $cSale->invoice_number }}</span>
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $cSale->created_at->format('d/m/Y H:i') }}</small>
                                            </td>
                                            <td>
                                                @if($cSale->credit_due_date)
                                                    <span class="badge {{ \Carbon\Carbon::parse($cSale->credit_due_date)->isPast() && $cSale->credit_status === 'pending' ? 'bg-danger text-white' : 'bg-light text-dark border' }}">
                                                        {{ \Carbon\Carbon::parse($cSale->credit_due_date)->format('d/m/Y') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted small">Sin plazo</span>
                                                @endif
                                            </td>
                                            <td class="text-end fw-bold">
                                                ${{ number_format($cSale->total, 0, ',', '.') }}
                                            </td>
                                            <td class="text-end text-success">
                                                ${{ number_format($cSale->credit_paid_amount, 0, ',', '.') }}
                                            </td>
                                            <td class="text-end fw-bold {{ $cSale->credit_pending_amount > 0 ? 'text-danger' : 'text-muted' }}">
                                                @if($cSale->isCancelled() || $cSale->credit_status === 'cancelled')
                                                    <span class="text-muted text-decoration-line-through">$0</span>
                                                @else
                                                    ${{ number_format($cSale->credit_pending_amount, 0, ',', '.') }}
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($cSale->isCancelled() || $cSale->credit_status === 'cancelled')
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill px-2 py-1">
                                                        <i class="bi bi-x-circle me-1"></i>Anulada
                                                    </span>
                                                @elseif($cSale->credit_status === 'paid')
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill px-2 py-1">
                                                        <i class="bi bi-check-circle me-1"></i>Saldada
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning bg-opacity-25 text-dark border border-warning rounded-pill px-2 py-1">
                                                        <i class="bi bi-clock me-1"></i>Pendiente
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-2">
                                                <div class="d-flex justify-content-end gap-1">
                                                    @if(! $cSale->isCancelled() && $cSale->credit_status !== 'cancelled' && $cSale->credit_pending_amount > 0)
                                                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3 fw-bold" 
                                                                onclick="openPaymentModal({{ $cSale->id }}, {{ $cSale->credit_pending_amount }}, '{{ $cSale->invoice_number }}')">
                                                            <i class="bi bi-wallet2 me-1"></i> Abonar
                                                        </button>
                                                    @endif
                                                    <a href="{{ route('prints.sales.receipt', $cSale) }}" target="_blank" class="btn btn-sm btn-light border rounded-pill px-2" title="Imprimir Tirilla 58mm">
                                                        <i class="bi bi-printer text-secondary"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted small">
                                                Este cliente no tiene facturas otorgadas a crédito.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Pestaña 2: Historial General de Ventas --}}
                    <div class="tab-pane fade p-3" id="sales-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="small text-uppercase text-muted">
                                        <th class="ps-2">Factura</th>
                                        <th>Fecha</th>
                                        <th>Canal</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-center">Tipo</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end pe-2">Tirilla</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($customer->sales as $sale)
                                        <tr>
                                            <td class="ps-2">
                                                <span class="font-monospace fw-bold text-dark">{{ $sale->invoice_number }}</span>
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $sale->created_at->format('d/m/Y H:i') }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border small">{{ strtoupper($sale->order_type ?? 'DIRECT') }}</span>
                                            </td>
                                            <td class="text-end">
                                                <span class="fw-bold text-dark">${{ number_format($sale->total, 0, ',', '.') }}</span>
                                            </td>
                                            <td class="text-center">
                                                @if($sale->is_credit)
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 small">Crédito</span>
                                                @else
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">Contado</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($sale->status === 'completed')
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small">Completada</span>
                                                @elseif ($sale->status === 'cancelled')
                                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 small">Anulada</span>
                                                @else
                                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 small">{{ $sale->status }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end pe-2">
                                                <a href="{{ route('prints.sales.receipt', $sale) }}" target="_blank" class="btn btn-sm btn-light border rounded-pill px-3" title="Ver tirilla 58mm">
                                                    <i class="bi bi-printer text-secondary"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted small">
                                                El cliente no tiene compras registradas en el sistema.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Pestaña 3: Historial de Abonos Recibidos --}}
                    <div class="tab-pane fade p-3" id="payments-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="small text-uppercase text-muted">
                                        <th class="ps-2">ID Pago</th>
                                        <th>Fecha y Hora</th>
                                        <th>Factura Asociada</th>
                                        <th>Método</th>
                                        <th>Recibido Por</th>
                                        <th class="text-end pe-2">Monto Abonado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($customer->creditPayments as $cp)
                                        <tr>
                                            <td class="ps-2 font-monospace fw-bold">#{{ $cp->id }}</td>
                                            <td>{{ $cp->paid_at ? $cp->paid_at->format('d/m/Y H:i:s') : '—' }}</td>
                                            <td>
                                                <span class="font-monospace text-dark">{{ $cp->sale->invoice_number ?? 'Venta #' . $cp->sale_id }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    {{ match($cp->method) {
                                                        'cash' => 'Efectivo',
                                                        'transfer' => 'Transferencia / Nequi',
                                                        'card' => 'Tarjeta',
                                                        default => ucfirst($cp->method)
                                                    } }}
                                                    {{ $cp->reference ? "({$cp->reference})" : '' }}
                                                </span>
                                            </td>
                                            <td>{{ $cp->user->name ?? 'Cajero' }}</td>
                                            <td class="text-end pe-2 fw-bold text-success fs-6">
                                                +${{ number_format($cp->amount, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted small">
                                                No se han registrado abonos de cartera para este cliente.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
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
                    <i class="bi bi-wallet2 text-success me-2"></i>Registrar Abono a Cartera
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body px-4 py-3">
                <div class="p-3 bg-light rounded-4 mb-3 border">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Cliente:</span>
                        <strong class="text-dark">{{ $customer->name }}</strong>
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
                        <label class="form-label fw-bold text-dark small mb-0">Monto del Abono ($) *</label>
                        <button type="button" class="btn btn-sm btn-link p-0 text-success text-decoration-none fw-semibold" id="btnPayFull">
                            Pagar Todo el Saldo
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
                        Este recaudo ingresará de inmediato al <strong>Cuadre de Caja de hoy</strong> bajo el usuario en sesión.
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top px-4 py-3">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold" style="background-color: #10B981; border-color: #10B981;">
                    <i class="bi bi-check-lg me-1"></i> Confirmar Recaudo
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentPendingAmount = 0;

function openPaymentModal(saleId, pendingAmount, invoiceNumber) {
    currentPendingAmount = pendingAmount;
    document.getElementById('modal_sale_id').value = saleId;
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
</script>
@endsection
