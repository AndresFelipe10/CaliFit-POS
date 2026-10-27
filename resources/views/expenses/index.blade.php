@extends('layouts.app')

@section('title', 'Gastos y Facturas de Compra | SaborCaliFit')

@section('content')
<div class="container-fluid px-2 px-md-3 py-3">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">
                <i class="bi bi-receipt-cutoff text-success me-2"></i>Gastos Operativos y Facturas
            </h1>
            <p class="text-muted small mb-0">Control de egresos, facturas de compras y cuentas por pagar (SaborCaliFit Bochalema).</p>
        </div>
        <div>
            <button type="button" class="btn btn-califit btn-sm px-3 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#createExpenseModal">
                <i class="bi bi-plus-circle me-1"></i> Registrar Gasto / Factura
            </button>
        </div>
    </div>

    {{-- Alert de regla contable --}}
    <div class="alert alert-light border shadow-sm d-flex align-items-center gap-2 py-2 px-3 mb-3 rounded-3 small text-dark">
        <i class="bi bi-info-circle text-success fs-5"></i>
        <span><strong>Regla contable estricta:</strong> Los gastos de mercancía no alteran stock físico ni generan movimientos en inventario. El inventario físico se controla exclusivamente desde el módulo de <strong>Inventario</strong>.</span>
    </div>

    {{-- Tarjetas de Resumen Consolidado --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 rounded-3 border-start border-4 border-primary">
                <small class="text-muted fw-semibold d-block">Gastos del Mes (Total)</small>
                <h4 class="fw-bold mb-0 text-dark">${{ number_format($totalMonthExpenses, 0, ',', '.') }}</h4>
                <small class="text-muted" style="font-size: 0.72rem;">Egresos del mes actual</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 rounded-3 border-start border-4 border-success">
                <small class="text-muted fw-semibold d-block">Total Pagado en Mes</small>
                <h4 class="fw-bold mb-0 text-success">${{ number_format($totalMonthPaid, 0, ',', '.') }}</h4>
                <small class="text-muted" style="font-size: 0.72rem;">Egresos liquidados</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 rounded-3 border-start border-4 border-warning">
                <small class="text-muted fw-semibold d-block">Pendiente por Pagar</small>
                <h4 class="fw-bold mb-0 text-warning-emphasis">${{ number_format($totalPending, 0, ',', '.') }}</h4>
                <small class="text-muted" style="font-size: 0.72rem;">Cuentas por pagar</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm p-3 rounded-3 border-start border-4 border-danger">
                <small class="text-muted fw-semibold d-block">Facturas Vencidas</small>
                <h4 class="fw-bold mb-0 text-danger">${{ number_format($overdueAmount, 0, ',', '.') }}</h4>
                <small class="text-danger fw-semibold" style="font-size: 0.72rem;">{{ $overdueCount }} factura(s) en mora</small>
            </div>
        </div>
    </div>

    {{-- Filtros de Búsqueda --}}
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('expenses.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Buscar</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Factura, descripción..." value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Categoría</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">-- Todas --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->value }}" {{ request('category') === $cat->value ? 'selected' : '' }}>
                                {{ $cat->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Estado</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">-- Todos --</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Pagada</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                        <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Vencida</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Proveedor</label>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">-- Todos --</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}" {{ (string)request('supplier_id') === (string)$sup->id ? 'selected' : '' }}>
                                {{ $sup->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Desde</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-12 col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-califit btn-sm w-100 fw-bold" title="Filtrar">
                        <i class="bi bi-funnel"></i>
                    </button>
                    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de Gastos --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3" style="width: 110px;">Emisión</th>
                        <th style="width: 130px;">Factura #</th>
                        <th>Proveedor / Descripción</th>
                        <th style="width: 140px;">Categoría</th>
                        <th class="text-end" style="width: 130px;">Monto</th>
                        <th style="width: 110px;">Vencimiento</th>
                        <th class="text-center" style="width: 120px;">Estado</th>
                        <th class="text-center" style="width: 90px;">Adjunto</th>
                        <th class="text-end pe-3" style="width: 130px;">Acciones</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse($expenses as $expense)
                        @php
                            $isOverdue = $expense->is_overdue;
                            $statusBadge = match($expense->status) {
                                'paid' => 'bg-success text-white',
                                'pending' => $isOverdue ? 'bg-danger text-white' : 'bg-warning text-dark',
                                default => 'bg-secondary text-white',
                            };
                            $statusLabel = match($expense->status) {
                                'paid' => 'Pagada',
                                'pending' => $isOverdue ? 'Vencida' : 'Pendiente',
                                default => $expense->status,
                            };
                            $detailPayload = [
                                'id' => $expense->id,
                                'invoice_number' => $expense->invoice_number ?: 'S/N',
                                'supplier' => $expense->supplier?->name ?: 'Gasto General / Interno',
                                'category' => $expense->category_label,
                                'amount' => '$' . number_format($expense->amount, 0, ',', '.'),
                                'issue_date' => $expense->issue_date->format('d/m/Y'),
                                'due_date' => $expense->due_date ? $expense->due_date->format('d/m/Y') : 'No aplica',
                                'status' => $expense->status,
                                'status_label' => $statusLabel,
                                'status_badge' => $statusBadge,
                                'is_overdue' => $isOverdue,
                                'paid_at' => $expense->paid_at ? $expense->paid_at->format('d/m/Y') : null,
                                'payment_method' => match($expense->payment_method) {
                                    'cash' => 'Efectivo',
                                    'transfer' => 'Transferencia / Nequi',
                                    'card' => 'Tarjeta / Datáfono',
                                    'other' => 'Otro',
                                    default => $expense->payment_method ?: 'No especificado',
                                },
                                'description' => $expense->description ?: 'Sin descripción u observaciones adicionales.',
                                'creator' => $expense->creator?->name ?: 'Sistema',
                                'created_at' => $expense->created_at->format('d/m/Y H:i'),
                                'has_attachment' => !empty($expense->attachment_path),
                                'attachment_url' => $expense->attachment_path ? route('expenses.attachment', $expense) : null,
                            ];
                        @endphp
                        <tr>
                            <td class="ps-3 text-muted">
                                {{ $expense->issue_date->format('d/m/Y') }}
                            </td>
                            <td class="font-monospace fw-semibold text-dark">
                                {{ $expense->invoice_number ?: 'S/N' }}
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $expense->supplier?->name ?: 'Gasto General' }}</div>
                                @if($expense->description)
                                    <small class="text-muted d-block text-truncate" style="max-width: 250px;">{{ $expense->description }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ $expense->category_label }}
                                </span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                ${{ number_format($expense->amount, 0, ',', '.') }}
                            </td>
                            <td class="text-muted">
                                {{ $expense->due_date ? $expense->due_date->format('d/m/Y') : '-' }}
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $statusBadge }} px-2 py-1 rounded-pill fw-semibold">
                                    @if($isOverdue)<i class="bi bi-exclamation-triangle-fill me-1"></i>@endif
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($expense->attachment_path)
                                    <a href="{{ route('expenses.attachment', $expense) }}" target="_blank" class="btn btn-outline-secondary btn-sm p-1" title="Ver comprobante privado">
                                        <i class="bi bi-paperclip fs-6"></i>
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-primary p-1 btn-view-expense" title="Ver detalles"
                                            data-expense="{{ json_encode($detailPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}"
                                            onclick="openExpenseDetailsModalFromButton(this)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    @if($expense->status === 'pending')
                                        <button type="button" class="btn btn-sm btn-outline-success p-1" title="Marcar como pagada"
                                                onclick="openPayModal({{ $expense->id }}, '{{ $expense->invoice_number }}', {{ $expense->amount }})">
                                            <i class="bi bi-check2-circle"></i>
                                        </button>
                                    @endif
                                    <form method="POST" action="{{ route('expenses.destroy', $expense) }}" class="d-inline"
                                          data-confirm="¿Seguro que deseas eliminar el gasto {{ $expense->invoice_number ? 'con factura ' . $expense->invoice_number : '' }} por valor de ${{ number_format($expense->amount, 0, ',', '.') }}? Esta acción no se puede deshacer."
                                          data-confirm-title="¿Eliminar Gasto?"
                                          data-confirm-btn="Sí, Eliminar Gasto">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-receipt-cutoff fs-2 d-block mb-2 text-secondary"></i>
                                No se encontraron gastos ni facturas registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $expenses->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal Registrar Gasto / Factura --}}
<div class="modal fade" id="createExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-success"></i>Registrar Gasto / Factura</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Categoría de Gasto <span class="text-danger">*</span></label>
                        <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                            <option value="">-- Seleccionar categoría --</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->value }}" {{ old('category') === $cat->value ? 'selected' : '' }}>{{ $cat->label() }}</option>
                            @endforeach
                        </select>
                        @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Proveedor (Opcional)</label>
                        <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">-- Sin proveedor / Gasto interno --</option>
                            @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}" {{ (string)old('supplier_id') === (string)$sup->id ? 'selected' : '' }}>{{ $sup->name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Monto ($ COP) <span class="text-danger">*</span></label>
                        <input type="text" inputmode="numeric" name="amount" class="form-control cop-currency-input @error('amount') is-invalid @enderror" placeholder="0" value="{{ old('amount') }}" required>
                        @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Número de Factura / Remisión</label>
                        <input type="text" name="invoice_number" class="form-control @error('invoice_number') is-invalid @enderror" placeholder="FAC-1234" value="{{ old('invoice_number') }}">
                        @error('invoice_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Fecha de Emisión <span class="text-danger">*</span></label>
                        <input type="date" name="issue_date" class="form-control @error('issue_date') is-invalid @enderror" value="{{ old('issue_date', date('Y-m-d')) }}" required>
                        @error('issue_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Fecha de Vencimiento</label>
                        <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror" value="{{ old('due_date') }}">
                        @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Descripción o Concepto</label>
                        <input type="text" name="description" class="form-control @error('description') is-invalid @enderror" placeholder="Ej: Compra de pollo, pago energía eléctrica Arbolatta Mall..." value="{{ old('description') }}">
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold d-block mb-2">Estado de Pago <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="expense-status-card p-3 rounded-3 border bg-warning-subtle border-warning shadow-sm"
                                     id="statusCardPending"
                                     onclick="selectExpenseStatus('pending')"
                                     style="cursor: pointer; transition: all 0.2s; border-width: 2px !important;">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-clock-history me-1"></i>Por Pagar</span>
                                        <i class="bi bi-check-circle-fill text-warning fs-5 check-icon" id="checkIconPending"></i>
                                    </div>
                                    <div class="fw-bold text-dark">Pendiente por Pagar</div>
                                    <div class="small text-muted" style="font-size: 0.78rem;">Cuenta por pagar con fecha de vencimiento (se liquida posteriormente)</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="expense-status-card p-3 rounded-3 border bg-white border-light-subtle"
                                     id="statusCardPaid"
                                     onclick="selectExpenseStatus('paid')"
                                     style="cursor: pointer; transition: all 0.2s; border-width: 2px !important;">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="badge bg-success px-2 py-1"><i class="bi bi-check2-all me-1"></i>Liquidado</span>
                                        <i class="bi bi-circle text-muted fs-5 check-icon" id="checkIconPaid"></i>
                                    </div>
                                    <div class="fw-bold text-dark">Pagado Inmediatamente</div>
                                    <div class="small text-muted" style="font-size: 0.78rem;">Desembolso de contado inmediato (se registra método de pago)</div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="status" id="expenseStatusInput" value="{{ old('status', 'pending') }}" required>
                    </div>
                    <div class="col-md-6 payment-fields" style="display: none;">
                        <label class="form-label small fw-semibold">Fecha de Pago <span class="text-danger">*</span></label>
                        <input type="date" name="paid_at" class="form-control @error('paid_at') is-invalid @enderror" value="{{ old('paid_at', date('Y-m-d')) }}">
                        @error('paid_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 payment-fields" style="display: none;">
                        <label class="form-label small fw-semibold">Método de Pago <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror">
                            <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Efectivo</option>
                            <option value="transfer" {{ old('payment_method') === 'transfer' ? 'selected' : '' }}>Transferencia / Nequi / Daviplata</option>
                            <option value="card" {{ old('payment_method') === 'card' ? 'selected' : '' }}>Tarjeta / Datáfono</option>
                            <option value="other" {{ old('payment_method') === 'other' ? 'selected' : '' }}>Otro</option>
                        </select>
                        @error('payment_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Comprobante / Factura Digital (PDF, JPG, PNG)</label>
                        <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="text-muted">Se almacenará en disco privado con cifrado/hashing seguro. Máx 3 MB.</small>
                        @error('attachment') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-califit btn-sm fw-bold px-4">Guardar Gasto</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Marcar como Pagada --}}
<div class="modal fade" id="payExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="payExpenseForm" class="modal-content">
            @csrf
            @method('PATCH')
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-check2-circle me-2"></i>Registrar Pago de Factura</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <p class="mb-2">Factura: <strong id="payInvoiceDisplay">-</strong></p>
                <p class="mb-3">Monto a liquidar: <strong id="payAmountDisplay" class="text-success fs-5">-</strong></p>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Fecha de Pago <span class="text-danger">*</span></label>
                    <input type="date" name="paid_at" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Método de Pago <span class="text-danger">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="cash">Efectivo</option>
                        <option value="transfer">Transferencia / Nequi / Daviplata</option>
                        <option value="card">Tarjeta / Datáfono</option>
                        <option value="other">Otro</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success btn-sm fw-bold px-4">Confirmar Pago</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Ver Detalles de Gasto / Factura --}}
<div class="modal fade" id="expenseDetailsModal" tabindex="-1" aria-labelledby="expenseDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-success bg-opacity-25 p-2 text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-receipt fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0" id="expenseDetailsModalLabel">Detalle de Factura / Gasto</h6>
                        <small class="text-white-50" id="detailInvoiceSubtitle">FAC-1234</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center py-2 px-3 bg-light rounded-3 mb-3 border">
                    <span class="small text-muted text-uppercase fw-semibold d-block mb-1">Monto Registrado</span>
                    <h3 class="fw-black text-dark mb-1" id="detailAmount">$0</h3>
                    <div id="detailStatusBadgeContainer">
                        <span class="badge bg-success px-3 py-1 rounded-pill fw-semibold" id="detailStatusBadge">Pagada</span>
                    </div>
                </div>

                <div class="row g-3 small">
                    <div class="col-6">
                        <span class="text-muted d-block">Proveedor / Beneficiario:</span>
                        <strong class="text-dark fs-6" id="detailSupplier">-</strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">Categoría de Gasto:</span>
                        <span class="badge bg-light text-dark border px-2 py-1" id="detailCategory">-</span>
                    </div>

                    <div class="col-6">
                        <span class="text-muted d-block">Fecha de Emisión:</span>
                        <strong class="text-dark" id="detailIssueDate">-</strong>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">Fecha de Vencimiento:</span>
                        <span class="text-dark" id="detailDueDate">-</span>
                    </div>

                    <div class="col-6" id="detailPaidAtCol">
                        <span class="text-muted d-block">Fecha de Pago:</span>
                        <span class="text-success fw-bold" id="detailPaidAt">-</span>
                    </div>
                    <div class="col-6" id="detailPaymentMethodCol">
                        <span class="text-muted d-block">Método de Pago:</span>
                        <span class="text-dark fw-semibold" id="detailPaymentMethod">-</span>
                    </div>

                    <div class="col-12">
                        <span class="text-muted d-block mb-1">Descripción / Concepto:</span>
                        <div class="p-2 bg-light rounded border text-secondary" id="detailDescription" style="min-height: 44px;">-</div>
                    </div>

                    <div class="col-6">
                        <span class="text-muted d-block">Registrado por:</span>
                        <span class="text-dark fw-semibold" id="detailCreator">-</span>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">Fecha de Registro:</span>
                        <span class="text-dark" id="detailCreatedAt">-</span>
                    </div>

                    <div class="col-12" id="detailAttachmentCol">
                        <span class="text-muted d-block mb-1">Comprobante Digital:</span>
                        <a href="#" id="detailAttachmentLink" target="_blank" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                            <i class="bi bi-paperclip"></i> Ver / Descargar Documento Adjunto
                        </a>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function selectExpenseStatus(status) {
    const cardPending = document.getElementById('statusCardPending');
    const cardPaid = document.getElementById('statusCardPaid');
    const iconPending = document.getElementById('checkIconPending');
    const iconPaid = document.getElementById('checkIconPaid');
    const statusInput = document.getElementById('expenseStatusInput');
    const paymentFields = document.querySelectorAll('.payment-fields');

    statusInput.value = status;

    if (status === 'pending') {
        cardPending.className = 'expense-status-card p-3 rounded-3 border bg-warning-subtle border-warning shadow-sm';
        iconPending.className = 'bi bi-check-circle-fill text-warning fs-5 check-icon';

        cardPaid.className = 'expense-status-card p-3 rounded-3 border bg-white border-light-subtle';
        iconPaid.className = 'bi bi-circle text-muted fs-5 check-icon';

        paymentFields.forEach(f => f.style.display = 'none');
    } else {
        cardPaid.className = 'expense-status-card p-3 rounded-3 border bg-success-subtle border-success shadow-sm';
        iconPaid.className = 'bi bi-check-circle-fill text-success fs-5 check-icon';

        cardPending.className = 'expense-status-card p-3 rounded-3 border bg-white border-light-subtle';
        iconPending.className = 'bi bi-circle text-muted fs-5 check-icon';

        paymentFields.forEach(f => f.style.display = 'block');
    }
}

function openPayModal(id, invoice, amount) {
    const form = document.getElementById('payExpenseForm');
    form.action = `/expenses/${id}/pay`;
    document.getElementById('payInvoiceDisplay').textContent = invoice || 'S/N';
    document.getElementById('payAmountDisplay').textContent = '$' + new Intl.NumberFormat('es-CO').format(amount);
    new bootstrap.Modal(document.getElementById('payExpenseModal')).show();
}

function openExpenseDetailsModalFromButton(btn) {
    try {
        const raw = btn.getAttribute('data-expense');
        const data = JSON.parse(raw);
        openExpenseDetailsModal(data);
    } catch (e) {
        console.error('Error al abrir detalle de gasto:', e);
    }
}

function openExpenseDetailsModal(data) {
    document.getElementById('detailInvoiceSubtitle').textContent = 'Factura #' + data.invoice_number;
    document.getElementById('detailAmount').textContent = data.amount;

    const badgeEl = document.getElementById('detailStatusBadge');
    badgeEl.className = 'badge px-3 py-1 rounded-pill fw-semibold ' + data.status_badge;
    badgeEl.textContent = data.status_label;

    document.getElementById('detailSupplier').textContent = data.supplier;
    document.getElementById('detailCategory').textContent = data.category;
    document.getElementById('detailIssueDate').textContent = data.issue_date;
    document.getElementById('detailDueDate').textContent = data.due_date;

    const paidAtCol = document.getElementById('detailPaidAtCol');
    const paymentMethodCol = document.getElementById('detailPaymentMethodCol');
    if (data.status === 'paid' && data.paid_at) {
        paidAtCol.style.display = 'block';
        paymentMethodCol.style.display = 'block';
        document.getElementById('detailPaidAt').textContent = data.paid_at;
        document.getElementById('detailPaymentMethod').textContent = data.payment_method;
    } else {
        paidAtCol.style.display = 'none';
        paymentMethodCol.style.display = 'none';
    }

    document.getElementById('detailDescription').textContent = data.description;
    document.getElementById('detailCreator').textContent = data.creator;
    document.getElementById('detailCreatedAt').textContent = data.created_at;

    const attachCol = document.getElementById('detailAttachmentCol');
    const attachLink = document.getElementById('detailAttachmentLink');
    if (data.has_attachment && data.attachment_url) {
        attachCol.style.display = 'block';
        attachLink.href = data.attachment_url;
    } else {
        attachCol.style.display = 'none';
    }

    new bootstrap.Modal(document.getElementById('expenseDetailsModal')).show();
}

document.addEventListener('DOMContentLoaded', function () {
    @if(old('status') === 'paid')
        selectExpenseStatus('paid');
    @endif

    @if($errors->any())
        const createModalEl = document.getElementById('createExpenseModal');
        if (createModalEl) {
            new bootstrap.Modal(createModalEl).show();
        }
    @endif
});
</script>
@endsection
