@extends('layouts.app')

@section('title', "Comanda {$order->order_number} | SaborCaliFit")

@section('content')
<div class="container-fluid px-2 px-md-3 py-3" style="max-width: 1300px;">

    {{-- ENCABEZADO DE LA COMANDA --}}
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Salón
                </a>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h4 class="fw-bold mb-0 text-dark">Comanda <span class="text-success">{{ $order->order_number }}</span></h4>
                        @if($order->table)
                            <span class="badge bg-dark rounded-pill px-3 py-1.5"><i class="bi bi-aspect-ratio me-1"></i> {{ $order->table->name }}</span>
                            @if(in_array($order->status, ['open', 'in_kitchen']) && $order->order_type === 'table')
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#changeTableModal">
                                    <i class="bi bi-arrow-left-right me-1"></i> Cambiar Mesa
                                </button>
                            @endif
                        @else
                            <span class="badge bg-info text-dark rounded-pill px-3 py-1.5"><i class="bi bi-bicycle me-1"></i> {{ $order->order_type_label }}</span>
                        @endif

                        @php
                            $statusStyle = match($order->status) {
                                'open', 'in_kitchen' => 'background-color: #F59E0B; color: #111827;',
                                'billed' => 'background-color: #F59E0B; color: #111827;',
                                'closed' => 'background-color: #059669; color: #FFFFFF;',
                                'cancelled' => 'background-color: #DC2626; color: #FFFFFF;',
                                default => 'background-color: #E5E7EB; color: #111827;'
                            };
                        @endphp
                        <span class="badge rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="{{ $statusStyle }}">{{ $order->status_label }}</span>
                    </div>
                    <small class="text-muted">
                        Mesero: <strong class="text-success"><i class="bi bi-person-badge me-1"></i>{{ $order->waiter_name ?: ($order->user->name ?? 'Personal') }}</strong> &bull;
                        Apertura: {{ $order->created_at->format('d/m/Y H:i') }} &bull;
                        Comensales: <strong>{{ $order->guest_count ?? 1 }}</strong>
                        @if($order->notes)
                            &bull; <em>"{{ $order->notes }}"</em>
                        @endif
                    </small>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                {{-- 1. Botón Cancelar Comanda --}}
                @if(!in_array($order->status, ['closed', 'cancelled']))
                    <button type="button" class="btn btn-outline-danger btn-sm py-2 px-3" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                        <i class="bi bi-x-circle me-1"></i> Cancelar Comanda
                    </button>
                @endif

                {{-- 2. Botón Pre-cuenta --}}
                @if(!in_array($order->status, ['closed', 'cancelled']))
                    <form action="{{ route('restaurant.orders.pre-bill', $order) }}" method="POST" target="_blank" onsubmit="setTimeout(() => window.location.reload(), 600)">
                        @csrf
                        <button type="submit" class="btn btn-outline-warning text-dark fw-bold btn-sm py-2 px-3">
                            <i class="bi bi-receipt me-1"></i> Pre-cuenta
                        </button>
                    </form>
                @endif

                {{-- 3. Botón Dividir Cuenta por Platos --}}
                @if(!in_array($order->status, ['closed', 'cancelled']))
                    <button type="button" class="btn btn-outline-success btn-sm py-2 px-3 fw-bold rounded-pill" onclick="openSplitBillModal()">
                        <i class="bi bi-pie-chart me-1"></i> Dividir Cuenta por Platos
                    </button>
                @endif

                {{-- 4. Botón Cobrar / Liquidar Total --}}
                @if(!in_array($order->status, ['closed', 'cancelled']))
                    <button type="button" class="btn btn-califit btn-sm py-2 px-4 shadow-sm fw-bold" onclick="openSettlementModal()">
                        <i class="bi bi-wallet2 me-1"></i> Cobrar Total
                    </button>
                @endif
            </div>
        </div>
    </div>

    @if($order->status === 'cancelled')
        <div class="alert alert-danger border-danger border-2 rounded-3 shadow-sm mb-3 p-3">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                    <i class="bi bi-x-octagon fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                        <h6 class="fw-bold text-danger mb-0">
                            <i class="bi bi-shield-exclamation me-1"></i> Comanda Cancelada (Registro de Auditoría)
                        </h6>
                        <span class="badge bg-danger text-white px-2.5 py-1 font-monospace">
                            <i class="bi bi-clock-history me-1"></i>{{ $order->cancelled_at ? $order->cancelled_at->format('d/m/Y H:i:s') : ($order->closed_at ? $order->closed_at->format('d/m/Y H:i:s') : 'Sin fecha') }}
                        </span>
                    </div>
                    <p class="mb-1 text-dark">
                        <strong>Motivo de Cancelación:</strong> 
                        <span class="fst-italic text-danger-emphasis fw-semibold">"{{ $order->cancellation_reason ?: 'Cancelación manual registrada por el operador' }}"</span>
                    </p>
                    <div class="small text-muted">
                        Cancelada por: <strong class="text-dark">{{ $order->cancelledByUser->name ?? ($order->waiter_name ?: 'Administrador') }}</strong> &bull;
                        Estado: <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Operación Bloqueada y Mesa Liberada</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row g-3">
        {{-- COLUMNA IZQUIERDA: TANDAS DE PREPARACIÓN DE LA COMANDA --}}
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-success me-2"></i>Tandas de Cocina</h5>
                @if(!in_array($order->status, ['closed', 'cancelled']))
                    <button type="button" class="btn btn-success btn-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#newBatchModal" style="background-color: #10B981; border: none;">
                        <i class="bi bi-plus-lg me-1"></i> Agregar Nueva Tanda
                    </button>
                @endif
            </div>

            @if($batches->isEmpty())
                <div class="card border-0 shadow-sm rounded-3 text-center py-5">
                    <i class="bi bi-egg-fried display-4 text-muted opacity-50 mb-2"></i>
                    <p class="text-muted mb-3">No hay tandas de preparación registradas en esta comanda.</p>
                    <div>
                        <button type="button" class="btn btn-califit btn-sm px-4" data-bs-toggle="modal" data-bs-target="#newBatchModal">
                            <i class="bi bi-plus-lg me-1"></i> Agregar Primeros Platos
                        </button>
                    </div>
                </div>
            @else
                @foreach($batches as $batchNumber => $items)
                    <div class="card border-0 shadow-sm rounded-3 mb-3">
                        <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom">
                            <div>
                                <span class="fw-bold text-dark fs-6"><i class="bi bi-fire text-danger me-1"></i> Tanda #{{ $batchNumber }}</span>
                                <small class="text-muted ms-2">{{ $items->first()->created_at->format('H:i:s') }}</small>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ route('prints.orders.kitchen', ['order' => $order, 'batch' => $batchNumber, 'reprint' => 1]) }}" target="_blank" class="btn btn-outline-dark btn-sm py-0 px-2" title="Imprimir Comanda de Cocina para esta Tanda">
                                    <i class="bi bi-printer me-1"></i> Ticket Cocina
                                </a>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th class="ps-3">Plato / Suplemento</th>
                                        <th class="text-center" style="width: 80px;">Cant.</th>
                                        <th class="text-end" style="width: 120px;">Unitario</th>
                                        <th class="text-end" style="width: 130px;">Subtotal</th>
                                        <th class="text-center pe-3" style="width: 90px;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                        @if($item->status === 'cancelled')
                                            <tr class="table-light" style="opacity: 0.7;">
                                                <td class="ps-3">
                                                    <div class="text-decoration-line-through text-muted fw-bold d-flex align-items-center">
                                                        {{ $item->product->nombre }}
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-2 py-0.5" style="font-size: 0.72rem;">
                                                            <i class="bi bi-x-circle me-1"></i>Eliminado / Anulado
                                                        </span>
                                                    </div>
                                                    <div class="small text-danger fst-italic mt-0.5" style="font-size: 0.75rem;">
                                                        "{{ $item->cancellation_reason ?: 'Cancelado en auditoría' }}"
                                                        @if($item->cancelledByUser)
                                                            &bull; Retirado por: <span class="fw-semibold">{{ $item->cancelledByUser->name }}</span>
                                                        @endif
                                                        @if($item->cancelled_at)
                                                            ({{ $item->cancelled_at->format('H:i') }})
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="text-center text-muted text-decoration-line-through small">{{ (float)$item->quantity }}</td>
                                                <td class="text-end text-muted text-decoration-line-through small">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                                <td class="text-end text-muted text-decoration-line-through small">$0</td>
                                                <td class="text-center pe-3">
                                                    <span class="badge bg-secondary-subtle text-secondary small py-1 px-2" style="font-size: 0.7rem;">Cancelado</span>
                                                </td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td class="ps-3">
                                                    <div class="fw-bold text-dark d-flex align-items-center">
                                                        {{ $item->product->nombre }}
                                                        @if($item->is_paid)
                                                            <span class="badge bg-success-subtle text-success border border-success border-opacity-25 ms-2 py-0.5" style="font-size: 0.72rem;">
                                                                <i class="bi bi-check2-all me-1"></i>Pagado
                                                            </span>
                                                        @endif
                                                    </div>
                                                    @if($item->notes)
                                                        <div class="text-muted fst-italic small"><i class="bi bi-chat-left-text me-1"></i>{{ $item->notes }}</div>
                                                    @endif

                                                    {{-- Adiciones seleccionadas --}}
                                                    @if($item->additions && $item->additions->count() > 0)
                                                        <div class="mt-1 ps-2 border-start border-2 border-success" style="font-size: 0.78rem;">
                                                            @foreach($item->additions as $add)
                                                                <div class="text-muted d-flex justify-content-between" style="max-width: 320px;">
                                                                    <span>+ {{ $add->addition->nombre ?? 'Adición' }} (x{{ (int)$add->quantity }})</span>
                                                                    <span class="text-success fw-bold">+${{ number_format($add->subtotal, 0, ',', '.') }}</span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-center fw-bold">{{ (float)$item->quantity }}</td>
                                                <td class="text-end text-muted small">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                                <td class="text-end fw-bold text-dark">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                                <td class="text-center pe-3">
                                                    @if($item->is_paid)
                                                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25 py-1 px-2 small" style="font-size: 0.7rem;">
                                                            <i class="bi bi-check2-all me-0.5"></i>Pagado
                                                        </span>
                                                    @elseif(!in_array($order->status, ['closed', 'cancelled']))
                                                        <button type="button" 
                                                                class="btn btn-outline-danger btn-sm rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" 
                                                                style="width: 32px; height: 32px;" 
                                                                title="Eliminar producto de la comanda"
                                                                onclick="openRemoveItemModal({{ $item->id }}, '{{ addslashes($item->product->nombre) }}', {{ (float)$item->quantity }}, {{ (float)$item->subtotal }}, {{ $batchNumber }})">
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    @else
                                                        <span class="text-muted small">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- COLUMNA DERECHA: RESUMEN MONETARIO --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 position-sticky" style="top: 80px;">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-calculator text-success me-1"></i> Resumen de Cuenta</h6>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal Consumo:</span>
                        <span class="fw-bold text-dark">${{ number_format($order->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if($order->order_type === 'delivery')
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Flete Domicilio:</span>
                            <span class="fw-bold text-dark">${{ number_format($order->delivery_fee ?? 0, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if($order->order_type === 'table')
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Propina Sugerida (10%):</span>
                            <span class="text-success small fw-bold">+${{ number_format(round($order->subtotal * 0.10, 2), 0, ',', '.') }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Total con Propina Sugerida:</span>
                            <span class="text-dark small fw-bold">${{ number_format(round($order->total + ($order->subtotal * 0.10), 2), 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if($order->tip > 0)
                        <div class="d-flex justify-content-between mb-2 bg-success-subtle p-1.5 rounded-2">
                            <span class="text-success small fw-semibold"><i class="bi bi-heart-fill me-1"></i>Propina Recaudada:</span>
                            <span class="text-success small fw-bold font-monospace">${{ number_format($order->tip, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <hr class="my-2 border-secondary opacity-25">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="h6 mb-0 fw-bold text-dark">TOTAL COMANDA:</span>
                        <h4 class="mb-0 fw-black text-success" style="color: #10B981 !important;">
                            ${{ number_format($order->total, 0, ',', '.') }}
                        </h4>
                    </div>

                    @if(!in_array($order->status, ['closed', 'cancelled']))
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-califit w-100 py-3 fw-bold fs-6 shadow-sm" onclick="openSettlementModal()">
                                <i class="bi bi-wallet2 me-1"></i> COBRAR TOTAL
                            </button>
                            <button type="button" class="btn btn-outline-dark w-100 py-2.5 fw-bold rounded-pill shadow-sm" onclick="openSplitBillModal()">
                                <i class="bi bi-pie-chart text-primary me-1"></i> Dividir Cuenta por Platos
                            </button>
                        </div>
                    @else
                        <div class="alert alert-secondary text-center py-2 mb-0 small">
                            Comanda finalizada y archivada.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL CAMBIAR MESA --}}
@if(in_array($order->status, ['open', 'in_kitchen']) && $order->order_type === 'table')
<div class="modal fade" id="changeTableModal" tabindex="-1" aria-labelledby="changeTableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('restaurant.orders.change-table', $order) }}" method="POST">
            @csrf
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header py-3 px-4 text-white" style="background-color: #111827; border-bottom: 3px solid #10B981;">
                    <div>
                        <h6 class="modal-title fw-bold mb-0 d-flex align-items-center" id="changeTableModalLabel">
                            <i class="bi bi-arrow-left-right text-success me-2 fs-5"></i>
                            Reasignar Mesa de la Comanda <span class="text-success ms-1">{{ $order->order_number }}</span>
                        </h6>
                        <small class="text-muted d-block mt-0.5" style="color: #9CA3AF !important;">
                            Mesa actual: <strong class="text-white">{{ $order->table->name ?? 'Sin Asignar' }}</strong>
                        </small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    @if(isset($availableTables) && $availableTables->isNotEmpty())
                        <div class="mb-3">
                            <label for="new_table_id" class="form-label fw-bold text-dark small">
                                Seleccionar Nueva Mesa Disponible: <span class="text-danger">*</span>
                            </label>
                            <select name="table_id" id="new_table_id" class="form-select form-select-lg border-2 shadow-sm" required style="border-color: #10B981; font-size: 0.95rem;">
                                <option value="" disabled selected>-- Seleccione una mesa libre --</option>
                                @foreach($availableTables as $availableTable)
                                    <option value="{{ $availableTable->id }}">
                                        {{ $availableTable->name }} — Capacidad: {{ $availableTable->capacity }} personas
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted mt-2 small">
                                <i class="bi bi-info-circle text-success me-1"></i>
                                Al confirmar, la mesa actual ({{ $order->table->name ?? 'Actual' }}) quedará libre y la nueva mesa pasará a estado ocupada.
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning d-flex align-items-center mb-0 p-3 rounded-3" role="alert" style="background-color: #FEF3C7; border: 1px solid #F59E0B; color: #92400E;">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
                            <div>
                                <strong class="d-block mb-1">No hay mesas libres disponibles en este momento.</strong>
                                <span class="small">Espere a que se desocupe o liquide alguna mesa en el salón para realizar el traslado.</span>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer p-3 bg-light border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    @if(isset($availableTables) && $availableTables->isNotEmpty())
                        <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #10B981; border-color: #10B981;">
                            <i class="bi bi-check-circle me-1"></i> Confirmar Cambio de Mesa
                        </button>
                    @else
                        <button type="button" class="btn btn-secondary fw-bold px-4" disabled>
                            <i class="bi bi-slash-circle me-1"></i> Confirmar Cambio de Mesa
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- MODAL CANCELAR COMANDA --}}
@if(!in_array($order->status, ['closed', 'cancelled']))
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header bg-light border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-exclamation-triangle text-danger fs-5"></i>
                    </div>
                    <h6 class="modal-title fw-bold text-dark mb-0" id="cancelOrderModalLabel">
                        ¿Deseas cancelar la comanda {{ $order->order_number }}?
                    </h6>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form action="{{ route('restaurant.orders.cancel', $order) }}" method="POST" class="m-0" id="cancelOrderForm">
                @csrf
                <div class="modal-body py-3 px-4 bg-white">
                    <div class="alert alert-warning border-0 d-flex align-items-center gap-2 py-2 px-3 mb-3 small">
                        <i class="bi bi-shield-lock-fill text-warning fs-5 flex-shrink-0"></i>
                        <span>Esta acción liberará la mesa asociada y quedará registrada en el historial de comandas con tu usuario, hora y motivo para auditoría.</span>
                    </div>

                    <div class="mb-3">
                        <label for="cancellation_reason" class="form-label fw-bold text-dark small mb-1">
                            Motivo de Cancelación (Obligatorio por Auditoría) <span class="text-danger">*</span>
                        </label>
                        <textarea name="cancellation_reason" id="cancellation_reason" class="form-control" rows="3" required minlength="5" maxlength="500" placeholder="Indique la razón detallada por la cual se cancela esta comanda (mínimo 5 caracteres)..."></textarea>
                    </div>

                    <div>
                        <span class="text-muted small d-block mb-1.5 fw-semibold">Motivos rápidos:</span>
                        <div class="d-flex flex-wrap gap-1.5">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2.5 small" onclick="setCancelReason('Cliente se retiró del local')">
                                [Cliente se retiró del local]
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2.5 small" onclick="setCancelReason('Error de digitación en apertura')">
                                [Error de digitación en apertura]
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2.5 small" onclick="setCancelReason('Cambio de canal a domicilio/llevar')">
                                [Cambio de canal a domicilio/llevar]
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2.5 small" onclick="setCancelReason('Producto o insumo no disponible')">
                                [Producto o insumo no disponible]
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-3 px-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">
                        Volver
                    </button>
                    <button type="submit" class="btn btn-danger px-4 fw-semibold">
                        <i class="bi bi-x-circle me-1"></i> Sí, Cancelar Comanda
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    function setCancelReason(reason) {
        const textarea = document.getElementById('cancellation_reason');
        if (textarea) {
            textarea.value = reason;
            textarea.focus();
        }
    }
</script>
@endif

{{-- MODAL ELIMINAR / ANULAR PRODUCTO DE COMANDA (AUDITORÍA) --}}
@if(!in_array($order->status, ['closed', 'cancelled']))
<div class="modal fade" id="removeOrderItemModal" tabindex="-1" aria-labelledby="removeOrderItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header bg-light border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-trash3 text-danger fs-5"></i>
                    </div>
                    <h6 class="modal-title fw-bold text-dark mb-0" id="removeOrderItemModalLabel">
                        ¿Eliminar producto de la comanda?
                    </h6>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form action="" method="POST" class="m-0" id="removeOrderItemForm">
                @csrf
                <div class="modal-body py-3 px-4 bg-white">
                    {{-- Resumen del producto a retirar --}}
                    <div class="p-3 rounded-3 bg-light border mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-secondary-subtle text-secondary mb-1" id="removeItemBatchBadge">Tanda #1</span>
                                <h6 class="fw-bold text-dark mb-0" id="removeItemNameLabel">Plato</h6>
                            </div>
                            <div class="text-end">
                                <span class="text-muted small d-block">Subtotal a descontar:</span>
                                <span class="fw-bold text-danger fs-6" id="removeItemSubtotalLabel">$0</span>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning border-0 d-flex align-items-center gap-2 py-2 px-3 mb-3 small">
                        <i class="bi bi-shield-lock-fill text-warning fs-5 flex-shrink-0"></i>
                        <span>Esta acción descontará el valor del producto del total de la comanda y quedará registrada en el historial de auditoría.</span>
                    </div>

                    <div class="mb-3">
                        <label for="removeItemReasonInput" class="form-label fw-bold text-dark small mb-1">
                            Motivo de eliminación / anulación (Obligatorio por Auditoría) <span class="text-danger">*</span>
                        </label>
                        <textarea name="cancellation_reason" id="removeItemReasonInput" class="form-control" rows="3" required minlength="5" maxlength="500" placeholder="Especifique la razón por la que se retira este producto de la comanda (mínimo 5 caracteres)..."></textarea>
                    </div>

                    <div>
                        <span class="text-muted small d-block mb-1.5 fw-semibold">Motivos rápidos:</span>
                        <div class="d-flex flex-wrap gap-1.5">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2.5 small" onclick="setRemoveItemReason('Error de digitación del mesero')">
                                [Error de digitación del mesero]
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2.5 small" onclick="setRemoveItemReason('Cliente cambió de plato')">
                                [Cliente cambió de plato]
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2.5 small" onclick="setRemoveItemReason('Cliente canceló este producto')">
                                [Cliente canceló este producto]
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-1 px-2.5 small" onclick="setRemoveItemReason('Insumo agotado en cocina')">
                                [Insumo agotado en cocina]
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-3 px-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-danger px-4 fw-semibold">
                        <i class="bi bi-trash3 me-1"></i> Confirmar y Eliminar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    function openRemoveItemModal(itemId, productName, quantity, subtotal, batchNumber) {
        const form = document.getElementById('removeOrderItemForm');
        if (form) {
            form.action = `/restaurant/orders/{{ $order->id }}/items/${itemId}/cancel`;
        }
        const nameEl = document.getElementById('removeItemNameLabel');
        if (nameEl) {
            nameEl.innerText = `${quantity}x ${productName}`;
        }
        const subtotalEl = document.getElementById('removeItemSubtotalLabel');
        if (subtotalEl) {
            subtotalEl.innerText = '$' + new Intl.NumberFormat('es-CO').format(subtotal);
        }
        const batchBadge = document.getElementById('removeItemBatchBadge');
        if (batchBadge) {
            batchBadge.innerText = `Tanda #${batchNumber}`;
        }
        const reasonInput = document.getElementById('removeItemReasonInput');
        if (reasonInput) {
            reasonInput.value = '';
        }

        const modalEl = document.getElementById('removeOrderItemModal');
        if (modalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            setTimeout(() => {
                if (reasonInput) reasonInput.focus();
            }, 300);
        }
    }

    function setRemoveItemReason(reason) {
        const textarea = document.getElementById('removeItemReasonInput');
        if (textarea) {
            textarea.value = reason;
            textarea.focus();
        }
    }
</script>
@endif

{{-- MODAL AGREGAR NUEVA TANDA DE PLATOS --}}
<div class="modal fade" id="newBatchModal" tabindex="-1" aria-labelledby="newBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold" id="newBatchModalLabel">
                    <i class="bi bi-plus-circle text-success me-1"></i> Agregar Platos / Suplementos (Nueva Tanda)
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Seleccionar Producto o Plato:</label>
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="batchProductSearch" class="form-control" placeholder="Buscar por nombre..." oninput="filterBatchProducts()" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" onclick="clearBatchProductSearch()" title="Limpiar"><i class="bi bi-x"></i></button>
                        </div>
                        <select id="batchProductSelect" class="form-select form-select-sm" onchange="onBatchProductChange()">
                            <option value="">-- Seleccionar de la lista --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" data-name="{{ $p->nombre }}" data-price="{{ $p->precio_venta }}" data-is-dish="{{ ($p->isDish() || $p->tipo_producto === 'PLATO') ? '1' : '0' }}">
                                    {{ $p->nombre }} - ${{ number_format($p->precio_venta, 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Cantidad:</label>
                        <input type="number" id="batchProductQty" class="form-control form-control-sm" value="1" min="1" step="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Notas / Preparación:</label>
                        <input type="text" id="batchProductNotes" class="form-control form-control-sm" placeholder="Ej: Término 3/4">
                    </div>
                </div>

                {{-- Sección de Adiciones para Platos --}}
                <div id="batchAdditionsSection" class="border rounded p-2 mb-3 bg-light d-none">
                    <label class="form-label small fw-semibold text-success mb-2">Adiciones Permitidas para el Plato:</label>
                    <div class="row row-cols-2 g-2" id="batchAdditionsList">
                        @foreach($adiciones as $add)
                            <div class="col">
                                <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded border">
                                    <div>
                                        <div class="small fw-semibold text-dark">{{ $add->nombre }}</div>
                                        <small class="text-success fw-bold">+${{ number_format($add->precio, 0, ',', '.') }}</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" onclick="adjustBatchAddQty({{ $add->id }}, -1)">-</button>
                                        <span class="small fw-bold px-1 batch-add-qty" id="batchAddQty_{{ $add->id }}" data-id="{{ $add->id }}" data-name="{{ $add->nombre }}" data-price="{{ $add->precio }}">0</span>
                                        <button type="button" class="btn btn-outline-secondary btn-sm p-0 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;" onclick="adjustBatchAddQty({{ $add->id }}, 1)">+</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="text-end mb-3">
                    <button type="button" class="btn btn-outline-success btn-sm px-3" onclick="addStagedBatchItem()">
                        <i class="bi bi-plus-lg me-1"></i> Añadir a la Tanda
                    </button>
                </div>

                <hr class="my-2 border-secondary opacity-25">

                <h6 class="fw-bold small text-dark mb-2">Platos a enviar en esta tanda:</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="stagedBatchTable">
                        <thead class="table-light small">
                            <tr>
                                <th>Ítem</th>
                                <th class="text-center">Cant.</th>
                                <th>Adiciones</th>
                                <th>Notas</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="stagedBatchTbody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3 small">Aún no has añadido ítems a la tanda.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-califit btn-sm px-4" id="btnSubmitBatch" onclick="submitBatch()">
                    <i class="bi bi-send-fill me-1"></i> Enviar Tanda a Cocina
                </button>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DE COBRO / LIQUIDACIÓN (RESTAURANT ORDER SETTLE - ESTILO IMAGEN 1) --}}
<div class="modal fade" id="settlementModal" tabindex="-1" aria-labelledby="settlementModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            {{-- Cabecera Limpia y Ergonómica --}}
            <div class="modal-header d-flex align-items-start justify-content-between pb-3 border-bottom bg-white py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-receipt fs-4"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="modal-title fw-bold text-dark mb-0">Datos de la Venta / Liquidación</h5>
                            <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.75rem;">
                                <i class="bi bi-calendar3 me-1"></i>{{ now()->format('d/m/Y') }}
                            </span>
                        </div>
                        <p class="text-muted small mb-0 mt-1">
                            Cierre de comanda <strong class="text-dark">{{ $order->order_number }}</strong> &bull; {{ $order->table ? (str_starts_with(trim($order->table->name), 'Mesa') ? $order->table->name : 'Mesa ' . $order->table->name) : $order->order_type_label }}
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4 bg-white">
                <div class="row g-4">
                    {{-- COLUMNA IZQUIERDA: MÉTODOS DE PAGO Y CRÉDITO --}}
                    <div class="col-lg-7">
                        {{-- Opción Venta a Crédito / Fiado --}}
                        <div class="card p-3 border rounded-3 mb-3 bg-light">
                            <div class="form-check form-switch d-flex align-items-center justify-content-between ps-0 mb-0">
                                <label class="form-check-label fw-bold text-dark cursor-pointer mb-0" for="settleCreditSwitch">
                                    <i class="bi bi-wallet2 text-danger me-1"></i> Despachar bajo Venta a Crédito / Fiado
                                </label>
                                <input class="form-check-input ms-2" type="checkbox" id="settleCreditSwitch" onchange="toggleSettleCreditMode(this.checked)" autocomplete="off" style="width: 2.5em; height: 1.3em;">
                            </div>
                            <div id="settleCreditFields" class="mt-3 pt-3 border-top d-none">
                                <div class="alert alert-warning py-2 px-3 rounded-3 small mb-2 d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill text-warning me-2 fs-5"></i>
                                    <div>
                                        <strong>Aislamiento de Caja:</strong> El inventario se descontará inmediatamente, pero no sumará dinero en caja hoy.
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <label class="form-label small fw-bold text-dark mb-1">Cliente Registrado Obligatorio: <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" placeholder="Filtrar por cédula, nombre o teléfono..." oninput="filterCustomerSelectBox(this.value, 'settleCreditCustomerSelect')" autocomplete="off">
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <select id="settleCreditCustomerSelect" class="form-select form-select-sm" onchange="onSettleCreditCustomerChange()">
                                            <option value="">-- Seleccione un cliente registrado --</option>
                                            @foreach($customers as $c)
                                                @if($c->document !== '222222222222')
                                                    <option value="{{ $c->id }}" data-doc="{{ $c->document }}" data-phone="{{ $c->phone }}" data-name="{{ $c->name }}" {{ $order->customer_id == $c->id ? 'selected' : '' }}>
                                                        {{ $c->name }} ({{ $c->document ?: 'Sin doc' }} - Tel: {{ $c->phone ?: 'Sin tel' }})
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <button type="button" class="btn btn-outline-success" onclick="openQuickCustomerModal('credit')" title="Crear Cliente Rápido">
                                            <i class="bi bi-person-plus-fill"></i>
                                        </button>
                                    </div>
                                    <div id="settleCreditCustomerError" class="text-danger small mt-1 d-none fw-semibold">
                                        <i class="bi bi-x-circle me-1"></i>El cliente debe tener teléfono registrado para otorgar crédito.
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-dark mb-1">Fecha Límite de Pago:</label>
                                        <input type="date" id="settleCreditDueDate" class="form-control form-control-sm" value="{{ now()->addDays(15)->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-bold text-dark mb-1">Estado de Cartera:</label>
                                        <input type="text" class="form-control form-control-sm bg-white" readonly value="PENDIENTE DE COBRO">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Sección de Pagos Inmediatos --}}
                        <div id="settleStandardPaymentsSection">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <label class="form-label small fw-semibold text-muted mb-0">
                                        <i class="bi bi-wallet2 me-1"></i>Métodos de Pago
                                    </label>
                                    <small class="text-muted d-block" style="font-size: 0.73rem;">El sistema calcula automáticamente el saldo restante para pagos mixtos.</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-success py-1 px-3 rounded-pill fw-semibold" onclick="addSettlePaymentLine()" id="btnSettleAddPayment">
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

                        <div id="settlePaymentLinesContainer" class="vstack gap-2 mb-2"></div>

                        {{-- Indicador dinámico de saldo / balance --}}
                        <div class="p-2 bg-light rounded-3 border text-center small mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Total cubierto: <strong id="settleModalCovered" class="text-dark font-monospace">$0</strong></span>
                                <div id="settleModalDiff"><span class="fw-bold text-success font-monospace">$0 (Exacto)</span></div>
                            </div>
                        </div>

                        {{-- Atajos rápidos de efectivo --}}
                        <div class="pt-2 border-top">
                            <span class="small text-muted d-block mb-1.5 fw-semibold"><i class="bi bi-lightning-charge me-1"></i>Atajos Rápidos de Efectivo:</span>
                            <div class="d-flex flex-wrap gap-1.5">
                                <button type="button" class="btn btn-sm btn-outline-secondary px-2.5 py-1 fw-semibold rounded-2" onclick="setSettleCashQuick('exact')">Exacto</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary px-2.5 py-1 fw-semibold rounded-2" onclick="setSettleCashQuick(10000)">$10.000</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary px-2.5 py-1 fw-semibold rounded-2" onclick="setSettleCashQuick(20000)">$20.000</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary px-2.5 py-1 fw-semibold rounded-2" onclick="setSettleCashQuick(50000)">$50.000</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary px-2.5 py-1 fw-semibold rounded-2" onclick="setSettleCashQuick(100000)">$100.000</button>
                            </div>
                        </div>

                        {{-- Caja destacada de Cambio / Vuelto --}}
                        <div class="p-3 rounded-3 mt-3" id="settleChangeBox" style="background-color: #ecfdf5; border: 1px solid #a7f3d0; transition: all 0.2s ease;">
                            <div id="settleChangeAmount">
                                <div class="small fw-bold text-muted mb-1 text-uppercase">Cambio / Vuelto al Cliente</div>
                                <h4 class="fw-black mb-0 text-success font-monospace">$0</h4>
                            </div>
                        </div>
                        </div>
                    </div>

                    {{-- COLUMNA DERECHA: RESUMEN Y ACCIÓN DE LIQUIDACIÓN --}}
                    <div class="col-lg-5">
                        <div class="card p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="border-bottom pb-3 mb-3">
                                    <h6 class="fw-bold text-uppercase text-muted small mb-2"><i class="bi bi-calculator me-1"></i>Resumen de Cobro</h6>

                                    {{-- Selector Triple Unificado de Cliente --}}
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold text-dark mb-1">
                                            <i class="bi bi-person text-success me-1"></i>Cliente Facturación:
                                        </label>
                                        <div class="input-group input-group-sm mb-1">
                                            <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" placeholder="Filtrar por cédula o nombre..." oninput="filterCustomerSelectBox(this.value, 'settleCustomerSelect')" autocomplete="off">
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <select id="settleCustomerSelect" class="form-select form-select-sm">
                                                <option value="" data-name="Consumidor Final" data-doc="222222222222">Consumidor Final (222222222222)</option>
                                                @foreach($customers as $c)
                                                    @if($c->document !== '222222222222')
                                                        <option value="{{ $c->id }}" data-name="{{ $c->name }}" data-doc="{{ $c->document }}" {{ ($order->customer_id == $c->id) ? 'selected' : '' }}>
                                                            {{ $c->name }} ({{ $c->document ?: 'Sin doc' }})
                                                        </option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <button type="button" class="btn btn-outline-success" onclick="openQuickCustomerModal('settle')" title="Crear Cliente Rápido">
                                                <i class="bi bi-person-plus-fill"></i>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Stepper Descuento (%) --}}
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label small fw-bold text-dark mb-0">
                                                <i class="bi bi-tag text-danger me-1"></i>Descuento (%):
                                            </label>
                                            <span class="small text-muted font-monospace" id="settleDiscountAmountLabel">$0</span>
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <button type="button" class="btn btn-outline-secondary px-2" onclick="changeSettleDiscount(-5)">-</button>
                                            <input type="number" id="settleDiscountPercent" class="form-control text-center fw-bold" min="0" max="100" step="5" value="0" oninput="onSettleDiscountInput()">
                                            <button type="button" class="btn btn-outline-secondary px-2" onclick="changeSettleDiscount(5)">+</button>
                                        </div>
                                    </div>

                                    {{-- Switches Táctiles de Propina e Impuesto --}}
                                    <div class="card p-2.5 border rounded-3 mb-2 bg-white">
                                        @if($order->order_type === 'table')
                                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                                <div>
                                                    <label class="form-check-label small fw-bold text-dark cursor-pointer d-block mb-0" for="settleTipSwitch">
                                                        <i class="bi bi-heart-fill text-success me-1"></i>Propina Voluntaria (10%)
                                                    </label>
                                                    <small class="text-muted" style="font-size: 0.72rem;">Sugerido voluntario</small>
                                                </div>
                                                <div class="form-check form-switch m-0">
                                                    <input class="form-check-input" type="checkbox" id="settleTipSwitch" onchange="toggleSettleTip(this.checked)" style="width: 2.3em; height: 1.25em; cursor: pointer;">
                                                </div>
                                            </div>
                                            <div id="settleTipCustomContainer" class="d-none pt-1.5 border-top mb-1">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-light text-muted small">Monto Propina: $</span>
                                                    <input type="text" inputmode="numeric" id="settleTipAmount" class="form-control form-control-sm text-end fw-bold text-success font-monospace cop-currency-input" value="0" oninput="onSettleTipAmountChange()">
                                                </div>
                                            </div>
                                        @endif
                                        <div class="d-flex align-items-center justify-content-between pt-1.5 {{ $order->order_type === 'table' ? 'border-top' : '' }}">
                                            <div>
                                                <label class="form-check-label small fw-bold text-dark cursor-pointer d-block mb-0" for="settleTaxSwitch">
                                                    <i class="bi bi-receipt text-info me-1"></i>Impuesto (INC 8%)
                                                </label>
                                                <small class="text-muted" style="font-size: 0.72rem;">Consumo gravable nacional</small>
                                            </div>
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" id="settleTaxSwitch" onchange="toggleSettleTax(this.checked)" style="width: 2.3em; height: 1.25em; cursor: pointer;">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Desglose Numérico --}}
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted small">Subtotal Consumo:</span>
                                        <span class="fw-semibold font-monospace" id="settleSummarySubtotal">${{ number_format($pendingSubtotal ?? $order->subtotal, 0, ',', '.') }}</span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-1 d-none" id="settleSummaryDiscountRow">
                                        <span class="text-danger small">Descuento (<span id="settleSummaryDiscountPercent">0</span>%):</span>
                                        <span class="fw-bold font-monospace text-danger" id="settleSummaryDiscount">-$0</span>
                                    </div>

                                    @if($order->order_type === 'delivery')
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted small">Flete Domicilio:</span>
                                            <span class="fw-semibold font-monospace">${{ number_format($order->delivery_fee ?? 0, 0, ',', '.') }}</span>
                                        </div>
                                    @endif

                                    <div class="d-flex justify-content-between align-items-center mb-1 d-none" id="settleSummaryTipRow">
                                        <span class="text-success small"><i class="bi bi-heart me-1"></i>Propina voluntaria:</span>
                                        <span class="fw-bold font-monospace text-success" id="settleSummaryTip">+$0</span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-1 d-none" id="settleSummaryTaxRow">
                                        <span class="text-info-emphasis small"><i class="bi bi-receipt me-1"></i>Impuesto INC (8%):</span>
                                        <span class="fw-bold font-monospace text-dark" id="settleSummaryTax">+$0</span>
                                    </div>

                                    {{-- Caja Destacada de Total a Cobrar --}}
                                    <div class="bg-white p-3 rounded-3 border mt-3 text-center shadow-sm">
                                        <span class="text-muted small d-block text-uppercase fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL A COBRAR</span>
                                        <span class="fw-black fs-2 font-monospace d-block" id="settleModalTotal" style="color: #10B981;">${{ number_format($pendingTotal ?? $order->total, 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <button type="button" class="btn btn-califit w-100 py-3 rounded-pill fw-bold shadow-sm d-flex justify-content-center align-items-center gap-2" style="min-height: 50px;" id="btnSubmitSettle" onclick="submitSettle()">
                                    <i class="bi bi-check2-circle fs-5"></i>
                                    <span class="fs-6">Registrar Venta y Liquidar</span>
                                </button>
                                <small class="text-muted text-center d-block mt-2" style="font-size: 0.72rem;">
                                    <i class="bi bi-printer me-1"></i>Genera e imprime el ticket de 80 mm y libera la mesa.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DE DIVISIÓN DE CUENTA POR PLATOS --}}
<div class="modal fade" id="splitBillModal" tabindex="-1" aria-labelledby="splitBillModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header d-flex align-items-center justify-content-between pb-3 border-bottom bg-white py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-pie-chart-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="modal-title fw-bold text-dark mb-0">División de Cuenta por Platos</h5>
                            <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.75rem;">{{ $order->table ? (str_starts_with(trim($order->table->name), 'Mesa') ? $order->table->name : 'Mesa ' . $order->table->name) : 'Salón' }}</span>
                        </div>
                        <p class="text-muted small mb-0 mt-0.5">
                            Comanda <strong class="text-dark">{{ $order->order_number }}</strong> &bull; Total comanda: <strong class="text-dark font-monospace">${{ number_format($order->total, 0, ',', '.') }}</strong>
                            &bull; Saldo pendiente: <strong class="text-success font-monospace" id="splitModalPendingAmount">${{ number_format($order->pending_amount, 0, ',', '.') }}</strong>
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-4 bg-white">
                <div class="row g-4">
                    {{-- COLUMNA IZQUIERDA: SELECCIÓN DE PLATOS --}}
                    <div class="col-lg-7 border-end">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0">Platos y Consumos de la Comanda</h6>
                                <span class="small text-muted">Seleccione los platos que pagará este comensal:</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold" id="btnToggleSelectAll" onclick="toggleSelectAllUnpaidItems()">
                                Marcar todos
                            </button>
                        </div>

                        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th style="width: 44px;" class="text-center">✓</th>
                                        <th>Plato / Consumo</th>
                                        <th class="text-center" style="width: 70px;">Cant.</th>
                                        <th class="text-end" style="width: 120px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="splitItemsTbody">
                                    @php
                                        $unpaidCount = 0;
                                    @endphp
                                    @foreach($order->items->where('status', '!=', 'cancelled') as $item)
                                        @if(!$item->is_paid)
                                            @php $unpaidCount++; @endphp
                                            <tr style="cursor: pointer;" onclick="toggleItemRowCheckbox(event, {{ $item->id }})">
                                                <td class="text-center" onclick="event.stopPropagation()">
                                                    <input type="checkbox" class="form-check-input split-item-checkbox" 
                                                           id="split_item_{{ $item->id }}"
                                                           value="{{ $item->id }}" 
                                                           data-subtotal="{{ (float)$item->subtotal }}" 
                                                           onchange="calculateItemSplitTotal()"
                                                           style="width: 1.25rem; height: 1.25rem; cursor: pointer;">
                                                </td>
                                                <td>
                                                    <label for="split_item_{{ $item->id }}" class="fw-bold text-dark mb-0 d-block" style="cursor: pointer;">
                                                        {{ $item->product->nombre }}
                                                    </label>
                                                    @if($item->additions && $item->additions->count() > 0)
                                                        <div class="small text-muted ps-2 border-start border-2 border-success mt-1">
                                                            @foreach($item->additions as $uAdd)
                                                                <div>+ {{ $uAdd->addition->nombre ?? 'Adición' }} (x{{ (int)$uAdd->quantity }})</div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="text-center font-monospace">{{ (float)$item->quantity }}</td>
                                                <td class="text-end fw-bold text-dark font-monospace">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                            </tr>
                                        @else
                                            <tr class="table-light opacity-75">
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input" checked disabled style="width: 1.25rem; height: 1.25rem;">
                                                </td>
                                                <td>
                                                    <div class="text-muted text-decoration-line-through">{{ $item->product->nombre }}</div>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-0.5 mt-1" style="font-size: 0.7rem;">
                                                        <i class="bi bi-check2-all me-1"></i>Pagado
                                                    </span>
                                                </td>
                                                <td class="text-center text-muted font-monospace">{{ (float)$item->quantity }}</td>
                                                <td class="text-end text-muted font-monospace">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                    @if($unpaidCount === 0)
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="bi bi-check-circle text-success fs-2 d-block mb-1"></i>
                                                ¡Todos los platos de la comanda ya han sido pagados!
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- COLUMNA DERECHA: LIQUIDACIÓN Y COBRO --}}
                    <div class="col-lg-5">
                        <div class="card p-3.5 border-0 rounded-4 bg-light h-100 d-flex flex-column justify-content-between shadow-sm">
                            <div style="max-height: 520px; overflow-y: auto; padding-right: 4px;">
                                <div class="border-bottom pb-2 mb-2.5">
                                    <h6 class="fw-bold text-dark mb-0.5"><i class="bi bi-receipt-cutoff text-success me-1"></i>Liquidación de Platos Seleccionados</h6>
                                    <small class="text-muted" id="splitItemsCountDisplay" style="font-size: 0.74rem;">0 plato(s) / ítem(s) seleccionados</small>
                                </div>

                                {{-- 1. Cliente y Descuento (%) --}}
                                <div class="row g-2 mb-2.5">
                                    <div class="col-7">
                                        <label class="form-label small fw-bold text-dark mb-1">
                                            <i class="bi bi-person text-success me-1"></i>Cliente / Comprador:
                                        </label>
                                        <div class="input-group input-group-sm mb-1">
                                            <span class="input-group-text bg-light text-muted"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" placeholder="Filtrar cliente..." oninput="filterCustomerSelectBox(this.value, 'splitItemCustomerSelect')" autocomplete="off">
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <select id="splitItemCustomerSelect" class="form-select form-select-sm">
                                                <option value="" data-name="Consumidor Final" data-doc="222222222222">Consumidor Final (222222222222)</option>
                                                @foreach($customers as $c)
                                                    @if($c->document !== '222222222222')
                                                        <option value="{{ $c->id }}" data-name="{{ $c->name }}" data-doc="{{ $c->document }}">
                                                            {{ $c->name }} ({{ $c->document ?: 'Sin doc' }})
                                                        </option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <button type="button" class="btn btn-outline-success" onclick="openQuickCustomerModal('split')" title="Crear Cliente Rápido">
                                                <i class="bi bi-person-plus-fill"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label small fw-bold text-dark mb-1">
                                            <i class="bi bi-tag text-danger me-1"></i>Descuento (%):
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <button type="button" class="btn btn-outline-secondary px-2" onclick="changeSplitDiscount(-5)">-</button>
                                            <input type="number" id="splitItemDiscountPercent" class="form-control text-center fw-bold" min="0" max="100" step="5" value="0" oninput="onSplitDiscountInput()">
                                            <button type="button" class="btn btn-outline-secondary px-2" onclick="changeSplitDiscount(5)">+</button>
                                        </div>
                                    </div>
                                </div>

                                {{-- 2. Switches Táctiles de Propina e Impuesto --}}
                                <div class="card p-2.5 border rounded-3 mb-2.5 bg-white">
                                    @if($order->order_type === 'table')
                                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                                            <div>
                                                <label class="form-check-label small fw-bold text-dark cursor-pointer d-block mb-0" for="splitItemTipSwitch">
                                                    <i class="bi bi-heart-fill text-success me-1"></i>Propina Voluntaria (10%)
                                                </label>
                                                <small class="text-muted" style="font-size: 0.72rem;">Sugerido voluntario</small>
                                            </div>
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" id="splitItemTipSwitch" onchange="toggleSplitTip(this.checked)" style="width: 2.3em; height: 1.25em; cursor: pointer;">
                                            </div>
                                        </div>
                                        <div id="splitItemTipCustomContainer" class="d-none pt-1.5 border-top mb-1">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light text-muted small">Monto Propina: $</span>
                                                <input type="text" inputmode="numeric" id="splitItemTipAmount" class="form-control form-control-sm text-end fw-bold text-success font-monospace cop-currency-input" value="0" oninput="onSplitTipAmountChange()">
                                            </div>
                                        </div>
                                    @endif
                                    <div class="d-flex align-items-center justify-content-between pt-1.5 {{ $order->order_type === 'table' ? 'border-top' : '' }}">
                                        <div>
                                            <label class="form-check-label small fw-bold text-dark cursor-pointer d-block mb-0" for="splitItemTaxSwitch">
                                                <i class="bi bi-receipt text-info me-1"></i>Impuesto (INC 8%)
                                            </label>
                                            <small class="text-muted" style="font-size: 0.72rem;">Consumo gravable nacional</small>
                                        </div>
                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input" type="checkbox" id="splitItemTaxSwitch" onchange="toggleSplitTax(this.checked)" style="width: 2.3em; height: 1.25em; cursor: pointer;">
                                        </div>
                                    </div>
                                </div>

                                {{-- 3. Tarjeta de Desglose Dinámico --}}
                                <div class="card p-2.5 border-0 rounded-3 mb-2.5" style="background-color: #F9FAFB; border: 1px solid #E5E7EB !important;">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted small">Subtotal Platos (<span id="splitSummaryCountBadge">0</span> ref):</span>
                                        <span class="fw-semibold font-monospace text-dark" id="splitSummarySubtotal">$0</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1 d-none" id="splitSummaryDiscountRow">
                                        <span class="text-danger small">Descuento (<span id="splitSummaryDiscountPercent">0</span>%):</span>
                                        <span class="fw-bold font-monospace text-danger" id="splitSummaryDiscount">-$0</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1 d-none" id="splitSummaryTipRow">
                                        <span class="text-success small"><i class="bi bi-heart me-1"></i>Propina voluntaria:</span>
                                        <span class="fw-bold font-monospace text-success" id="splitSummaryTip">+$0</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-1 d-none" id="splitSummaryTaxRow">
                                        <span class="text-info-emphasis small"><i class="bi bi-receipt me-1"></i>Impuesto (INC 8%):</span>
                                        <span class="fw-bold font-monospace text-dark" id="splitSummaryTax">+$0</span>
                                    </div>
                                    <hr class="my-1.5 border-secondary opacity-25">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark small text-uppercase">Total a Liquidar:</span>
                                        <h3 class="fw-black font-monospace mb-0" id="splitItemsSelectedTotal" style="color: #10B981;">$0</h3>
                                    </div>
                                </div>

                                {{-- 4. Métodos de Pago Mixtos y Atajos de Efectivo --}}
                                <div class="mb-2.5">
                                    <div class="d-flex justify-content-between align-items-center mb-1.5">
                                        <label class="form-label small fw-bold text-dark mb-0">
                                            <i class="bi bi-wallet2 text-success me-1"></i>Métodos de Pago:
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-success py-0.5 px-2.5 rounded-pill fw-semibold" onclick="addSplitPaymentLine()" id="btnSplitAddPayment" style="font-size: 0.74rem;">
                                            <i class="bi bi-plus-circle me-1"></i> Agregar otro medio
                                        </button>
                                    </div>

                                    {{-- Alerta informativa cuando no hay platos seleccionados --}}
                                    <div id="splitNoItemsAlert" class="alert alert-info py-2 px-3 rounded-3 small mb-2 d-flex align-items-center">
                                        <i class="bi bi-info-circle-fill me-2 fs-5 text-info"></i>
                                        <span>Seleccione al menos un plato en la columna izquierda.</span>
                                    </div>

                                    <div id="splitPaymentLinesContainer" class="vstack gap-1.5 mb-2"></div>

                                    {{-- Barra de Resumen en Tiempo Real y Atajos Rápidos de Efectivo --}}
                                    <div class="pt-2 border-top" id="splitCashShortcuts">
                                        <div class="d-flex align-items-center justify-content-between mb-1.5">
                                            <span class="small text-muted fw-semibold" style="font-size: 0.74rem;">
                                                <i class="bi bi-shield-check text-success me-1"></i>Total cubierto: <strong class="text-dark font-monospace" id="splitCoveredText">$0</strong>
                                            </span>
                                            <span class="badge bg-light text-muted border font-monospace px-2 py-1" id="splitCoveredBadge" style="font-size: 0.72rem;">$0 (Exacto)</span>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span class="small text-muted fw-semibold" style="font-size: 0.72rem;"><i class="bi bi-lightning-charge text-warning me-1"></i>Atajos Efectivo:</span>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0.5 fw-semibold rounded-2" style="font-size: 0.74rem;" onclick="setSplitCashQuick('exact')">✓ Exacto</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0.5 fw-semibold rounded-2" style="font-size: 0.74rem;" onclick="setSplitCashQuick(10000)">$10.000</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0.5 fw-semibold rounded-2" style="font-size: 0.74rem;" onclick="setSplitCashQuick(20000)">$20.000</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0.5 fw-semibold rounded-2" style="font-size: 0.74rem;" onclick="setSplitCashQuick(50000)">$50.000</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary px-2 py-0.5 fw-semibold rounded-2" style="font-size: 0.74rem;" onclick="setSplitCashQuick(100000)">$100.000</button>
                                        </div>
                                    </div>

                                    {{-- Caja Destacada de Cambio / Vuelto al Cliente --}}
                                    <div class="p-2.5 rounded-3 mt-2" id="splitChangeBox" style="background-color: #ecfdf5; border: 1px solid #a7f3d0; transition: all 0.2s ease;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small fw-bold text-muted text-uppercase" style="font-size: 0.72rem;">Cambio / Vuelto al cliente:</span>
                                            <h5 class="fw-black mb-0 text-success font-monospace" id="splitChangeDisplay">$0</h5>
                                        </div>
                                    </div>
                                </div>

                                {{-- 5. Observaciones de Facturación (Opcional) --}}
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold text-muted mb-1">Observaciones de Facturación (Opcional):</label>
                                    <input type="text" id="splitItemNotes" class="form-control form-control-sm" placeholder="Ej: Pago individual comensal 1, transferencia aprobada...">
                                </div>
                            </div>

                            {{-- 6. Botones de Acción --}}
                            <div class="mt-3 pt-2 border-top">
                                <div class="d-flex flex-column gap-2">
                                    <button type="button" class="btn btn-outline-secondary w-100 py-2 rounded-pill fw-bold" id="btnPartialPreBill" onclick="printPartialPreBill()">
                                        <i class="bi bi-printer me-1"></i> Imprimir Pre-cuenta (Platos Seleccionados)
                                    </button>
                                    <button type="button" class="btn btn-success w-100 py-2.5 rounded-pill fw-bold shadow-sm d-flex justify-content-center align-items-center gap-2" id="btnSubmitItemSplit" onclick="submitItemSplit()" style="background-color: #10B981; border-color: #10B981;">
                                        <i class="bi bi-cash-stack fs-5"></i>
                                        <span>Cobrar Platos e Imprimir Factura</span>
                                    </button>
                                </div>
                                <small class="text-muted text-center d-block mt-1.5" style="font-size: 0.72rem;">
                                    Descuenta los platos de la comanda y mantiene pendientes los restantes hasta liberar la mesa.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL REGISTRO RÁPIDO DE CLIENTE --}}
<div class="modal fade" id="quickCustomerModal" tabindex="-1" aria-labelledby="quickCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form id="quickCustomerForm" onsubmit="submitQuickCustomer(event)" class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            @csrf
            <div class="modal-header py-2.5 px-3 text-white" style="background-color: #111827; border-bottom: 3px solid #10B981;">
                <h6 class="modal-title fw-bold mb-0">
                    <i class="bi bi-person-plus text-success me-1"></i>Nuevo Cliente Rápido
                </h6>
                <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3 bg-white">
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark mb-1">Nombre Completo: <span class="text-danger">*</span></label>
                    <input type="text" id="quickCustomerName" name="name" class="form-control form-control-sm" required minlength="3" maxlength="100" placeholder="Nombre del cliente">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark mb-1">Documento / Cédula:</label>
                    <input type="text" id="quickCustomerDoc" name="document_number" inputmode="numeric" minlength="5" maxlength="12" class="form-control form-control-sm" placeholder="Ej. 1144...">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-dark mb-1">Teléfono:</label>
                    <input type="tel" id="quickCustomerPhone" name="phone" inputmode="numeric" minlength="7" maxlength="10" class="form-control form-control-sm" placeholder="Ej. 315...">
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light btn-sm border px-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success btn-sm px-3 fw-bold" style="background-color: #10B981; border-color: #10B981;" id="btnSubmitQuickCustomer">
                    Guardar y Asignar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const ORDER_BASE_TOTAL = {{ (float)($pendingTotal ?? $order->total) }};
const ORDER_PENDING_SUBTOTAL = {{ (float)($pendingSubtotal ?? $order->subtotal) }};
const ORDER_DELIVERY_FEE = {{ $order->order_type === 'delivery' ? (float)($order->delivery_fee ?? 0) : 0 }};
let ORDER_TOTAL = ORDER_BASE_TOTAL;
const ORDER_ITEMS_DATA = @json($orderItemsData ?? []);

let stagedBatchItems = [];
let settlePaymentLines = [];
let splitPaymentLines = [];

let allBatchOptions = [];

document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('batchProductSelect');
    if (sel) {
        allBatchOptions = Array.from(sel.querySelectorAll('option')).map(opt => ({
            value: opt.value,
            text: opt.textContent.trim(),
            name: opt.dataset.name || opt.textContent.split(' - ')[0].trim(),
            price: opt.dataset.price || '0',
            isDish: opt.dataset.isDish || '0'
        }));
    }
});

function filterBatchProducts() {
    const searchInput = document.getElementById('batchProductSearch');
    const query = (searchInput ? searchInput.value : '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
    const sel = document.getElementById('batchProductSelect');
    if (!sel || !allBatchOptions.length) return;

    sel.innerHTML = '';
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = '-- Seleccionar de la lista --';
    sel.appendChild(defaultOpt);

    let matchCount = 0;
    let singleMatchValue = '';

    allBatchOptions.forEach(item => {
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
        onBatchProductChange(false);
    }
}

function clearBatchProductSearch() {
    const searchInput = document.getElementById('batchProductSearch');
    if (searchInput) searchInput.value = '';
    filterBatchProducts();
    const sel = document.getElementById('batchProductSelect');
    if (sel) {
        sel.value = '';
        onBatchProductChange(false);
    }
}

// Manejo de nueva tanda
function onBatchProductChange(syncInput = true) {
    const sel = document.getElementById('batchProductSelect');
    const opt = sel.options[sel.selectedIndex];
    const isDish = opt ? opt.dataset.isDish === '1' : false;
    const sec = document.getElementById('batchAdditionsSection');

    if (syncInput && opt && opt.value) {
        const searchInput = document.getElementById('batchProductSearch');
        if (searchInput) {
            searchInput.value = opt.dataset.name || opt.text.split(' - ')[0].trim();
        }
    }

    if (isDish) {
        sec.classList.remove('d-none');
    } else {
        sec.classList.add('d-none');
        // Reset adiciones
        document.querySelectorAll('.batch-add-qty').forEach(el => el.textContent = '0');
    }
}

function adjustBatchAddQty(id, delta) {
    const el = document.getElementById(`batchAddQty_${id}`);
    if (!el) return;
    let qty = parseInt(el.textContent) || 0;
    qty = Math.max(0, qty + delta);
    el.textContent = qty;
}

function addStagedBatchItem() {
    const sel = document.getElementById('batchProductSelect');
    const productId = parseInt(sel.value);
    if (!productId) {
        califitNotify('Por favor selecciona un producto o plato para agregar.', 'warning', 'Plato Requerido');
        return;
    }

    const opt = sel.options[sel.selectedIndex];
    const productName = opt.dataset.name || opt.text.split(' - ')[0].trim();
    const qty = parseFloat(document.getElementById('batchProductQty').value) || 1;
    const notes = document.getElementById('batchProductNotes').value.trim();

    const selectedAdds = [];
    document.querySelectorAll('.batch-add-qty').forEach(el => {
        const aQty = parseInt(el.textContent) || 0;
        if (aQty > 0) {
            selectedAdds.push({
                addition_id: parseInt(el.dataset.id),
                name: el.dataset.name,
                quantity: aQty
            });
        }
    });

    stagedBatchItems.push({
        product_id: productId,
        name: productName,
        quantity: qty,
        notes: notes || null,
        additions: selectedAdds
    });

    // Resetear formulario
    clearBatchProductSearch();
    document.getElementById('batchProductQty').value = 1;
    document.getElementById('batchProductNotes').value = '';
    document.querySelectorAll('.batch-add-qty').forEach(el => el.textContent = '0');
    document.getElementById('batchAdditionsSection').classList.add('d-none');

    renderStagedBatchTable();
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

function renderStagedBatchTable() {
    const tbody = document.getElementById('stagedBatchTbody');
    tbody.innerHTML = '';

    if (stagedBatchItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3 small">Aún no has añadido ítems a la tanda.</td></tr>';
        return;
    }

    stagedBatchItems.forEach((item, index) => {
        const tr = document.createElement('tr');
        const addsStr = item.additions.map(a => `+ ${escapeHtml(a.name)} (x${escapeHtml(a.quantity)})`).join(', ') || '-';
        tr.innerHTML = `
            <td class="fw-semibold">${escapeHtml(item.name)}</td>
            <td class="text-center fw-bold">${escapeHtml(item.quantity)}</td>
            <td class="small text-muted">${addsStr}</td>
            <td class="small text-muted">${escapeHtml(item.notes || '-')}</td>
            <td class="text-end">
                <button type="button" class="btn btn-link text-danger p-0" onclick="removeStagedBatchItem(${index})"><i class="bi bi-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function removeStagedBatchItem(index) {
    stagedBatchItems.splice(index, 1);
    renderStagedBatchTable();
}

async function submitBatch() {
    if (stagedBatchItems.length === 0) {
        califitNotify('Debes agregar al menos un plato a la tanda para enviar a cocina.', 'warning', 'Tanda Vacía');
        return;
    }

    const payload = {
        items: stagedBatchItems.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity,
            notes: item.notes,
            additions: item.additions.map(a => ({
                addition_id: a.addition_id,
                quantity: a.quantity
            }))
        }))
    };

    const btn = document.getElementById('btnSubmitBatch');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';

    try {
        const res = await fetch("{{ route('restaurant.orders.add-items', $order) }}", {
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
            // Abrir ticket de cocina automáticamente para la nueva tanda
            window.open(`/prints/orders/{{ $order->id }}/kitchen/${data.batch_number}`, '_blank', 'width=340,height=620');
            location.reload();
        } else {
            califitNotify('Error al agregar tanda: ' + (data.message || JSON.stringify(data.errors || data)), 'danger', 'Error al Guardar');
        }
    } catch (err) {
        console.error(err);
        califitNotify('Ocurrió un error al enviar la tanda.', 'danger', 'Error de Red');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Enviar Tanda a Cocina';
    }
}

// ==========================================
// REGISTRO RÁPIDO DE CLIENTE (MODAL)
// ==========================================
let quickCustomerTarget = 'split';

function openQuickCustomerModal(target = 'split') {
    quickCustomerTarget = target;
    const form = document.getElementById('quickCustomerForm');
    if (form) form.reset();
    const modal = new bootstrap.Modal(document.getElementById('quickCustomerModal'));
    modal.show();
}

async function submitQuickCustomer(e) {
    e.preventDefault();
    const name = document.getElementById('quickCustomerName').value.trim();
    const doc = document.getElementById('quickCustomerDoc').value.trim();
    const phone = document.getElementById('quickCustomerPhone').value.trim();

    if (!name) {
        califitNotify('El nombre del cliente es obligatorio.', 'warning', 'Validación');
        return;
    }

    const btn = document.getElementById('btnSubmitQuickCustomer');
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
                phone: phone || null
            })
        });

        const data = await res.json();
        if (res.ok && data.success && data.customer) {
            const newCust = data.customer;
            const splitSelect = document.getElementById('splitItemCustomerSelect');
            const settleSelect = document.getElementById('settleCustomerSelect');
            const settleCreditSelect = document.getElementById('settleCreditCustomerSelect');

            const docVal = newCust.document_number || newCust.document || '';
            const phoneVal = newCust.phone || '';

            // 1. Selector en división de platos (#splitBillModal)
            if (splitSelect) {
                const optTextSplit = `${newCust.name} (${docVal || 'S/D'}) - Tel: ${phoneVal || 'S/T'}`;
                const newOptSplit = new Option(optTextSplit, newCust.id, true, true);
                newOptSplit.dataset.name = newCust.name;
                newOptSplit.dataset.doc = docVal || '222222222222';
                newOptSplit.dataset.phone = phoneVal;
                splitSelect.add(newOptSplit);
            }

            // 2. Selector de facturación en liquidación (#settlementModal)
            if (settleSelect) {
                const optTextSettle = `${newCust.name} (${docVal || 'S/D'}) - Tel: ${phoneVal || 'S/T'}`;
                const newOptSettle = new Option(optTextSettle, newCust.id, true, true);
                newOptSettle.dataset.name = newCust.name;
                newOptSettle.dataset.doc = docVal || '222222222222';
                newOptSettle.dataset.phone = phoneVal;
                settleSelect.add(newOptSettle);
            }

            // 3. Selector de crédito obligatorio (#settlementModal)
            if (settleCreditSelect) {
                const optTextCredit = `${newCust.name} (${docVal || 'S/D'}) - Tel: ${phoneVal || 'S/T'}`;
                const newOptCredit = new Option(optTextCredit, newCust.id, true, true);
                newOptCredit.dataset.doc = docVal;
                newOptCredit.dataset.phone = phoneVal;
                newOptCredit.dataset.name = newCust.name;
                settleCreditSelect.add(newOptCredit);
            }

            // Seleccionar y disparar eventos según el origen del modal
            if (quickCustomerTarget === 'split' && splitSelect) {
                splitSelect.value = newCust.id;
                splitSelect.dispatchEvent(new Event('change'));
            } else if (quickCustomerTarget === 'credit' && settleCreditSelect) {
                settleCreditSelect.value = newCust.id;
                settleCreditSelect.dispatchEvent(new Event('change'));
                if (typeof onSettleCreditCustomerChange === 'function') {
                    onSettleCreditCustomerChange();
                }
                if (settleSelect) {
                    settleSelect.value = newCust.id;
                    settleSelect.dispatchEvent(new Event('change'));
                }
            } else {
                if (settleSelect) {
                    settleSelect.value = newCust.id;
                    settleSelect.dispatchEvent(new Event('change'));
                }
                if (settleCreditSelect) {
                    settleCreditSelect.value = newCust.id;
                    settleCreditSelect.dispatchEvent(new Event('change'));
                    if (typeof onSettleCreditCustomerChange === 'function') {
                        onSettleCreditCustomerChange();
                    }
                }
            }

            // Cerrar el modal rápido limpiamente
            const modalEl = document.getElementById('quickCustomerModal');
            const bsModal = bootstrap.Modal.getInstance(modalEl);
            if (bsModal) {
                bsModal.hide();
            }

            // Preservar estado modal-open si el modal principal sigue abierto debajo
            setTimeout(() => {
                if (document.querySelector('#settlementModal.show') || document.querySelector('#splitBillModal.show')) {
                    document.body.classList.add('modal-open');
                }
            }, 300);

            califitNotify(data.message || `Cliente '${newCust.name}' registrado exitosamente.`, 'success', 'Cliente Registrado');
        } else {
            const errMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error al guardar cliente');
            califitNotify('Error al guardar cliente: ' + errMsg, 'danger', 'Error al Guardar');
        }
    } catch (err) {
        console.error(err);
        califitNotify('Ocurrió un error al guardar el cliente.', 'danger', 'Error de Red');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Guardar y Asignar';
    }
}

// ==========================================
// LIQUIDACIÓN TOTAL (COBRAR TOTAL / SETTLEMENT)
// ==========================================
let settleDiscountPercent = 0;
let settleTipActive = false;
let settleTipAmount = 0;
let settleTaxActive = false;

function openSettlementModal() {
    settleDiscountPercent = 0;
    const discInp = document.getElementById('settleDiscountPercent');
    if (discInp) discInp.value = 0;
    settleTipActive = false;
    const tipSw = document.getElementById('settleTipSwitch');
    if (tipSw) tipSw.checked = false;
    const tipCont = document.getElementById('settleTipCustomContainer');
    if (tipCont) tipCont.classList.add('d-none');
    settleTaxActive = false;
    const taxSw = document.getElementById('settleTaxSwitch');
    if (taxSw) taxSw.checked = false;

    const creditSw = document.getElementById('settleCreditSwitch');
    if (creditSw) {
        creditSw.checked = false;
        toggleSettleCreditMode(false);
    }

    recalculateSettleOrderTotal();

    settlePaymentLines = [{
        method: 'cash',
        amount: ORDER_TOTAL,
        cash_received: ORDER_TOTAL,
        reference: ''
    }];

    renderSettlePaymentLines();
    const modal = new bootstrap.Modal(document.getElementById('settlementModal'));
    modal.show();
}

function changeSettleDiscount(delta) {
    let current = parseInt(document.getElementById('settleDiscountPercent').value) || 0;
    current = Math.max(0, Math.min(100, current + delta));
    document.getElementById('settleDiscountPercent').value = current;
    onSettleDiscountInput();
}

function onSettleDiscountInput() {
    const input = document.getElementById('settleDiscountPercent');
    settleDiscountPercent = Math.max(0, Math.min(100, parseFloat(input.value) || 0));
    input.value = settleDiscountPercent;
    recalculateSettleOrderTotal();
}

function toggleSettleTip(checked) {
    settleTipActive = checked;
    const container = document.getElementById('settleTipCustomContainer');
    if (checked) {
        if (container) container.classList.remove('d-none');
        settleTipAmount = Math.round(ORDER_PENDING_SUBTOTAL * 0.10);
        const amtInput = document.getElementById('settleTipAmount');
        if (amtInput) amtInput.value = settleTipAmount;
    } else {
        if (container) container.classList.add('d-none');
        settleTipAmount = 0;
    }
    recalculateSettleOrderTotal();
}

function onSettleTipAmountChange() {
    const val = parseFloat(document.getElementById('settleTipAmount').value) || 0;
    settleTipAmount = Math.max(0, val);
    recalculateSettleOrderTotal();
}

function toggleSettleTax(checked) {
    settleTaxActive = checked;
    recalculateSettleOrderTotal();
}

function recalculateSettleOrderTotal() {
    const subtotal = ORDER_PENDING_SUBTOTAL;
    const discount = Math.round(subtotal * (settleDiscountPercent / 100));
    const baseAfterDiscount = Math.max(0, subtotal - discount);
    const tax = settleTaxActive ? Math.round(baseAfterDiscount * 0.08) : 0;
    const tip = settleTipActive ? settleTipAmount : 0;
    const delivery = ORDER_DELIVERY_FEE;

    ORDER_TOTAL = Math.max(0, Math.round(subtotal - discount + delivery + tax + tip));

    const discRow = document.getElementById('settleSummaryDiscountRow');
    if (discRow) {
        if (discount > 0) {
            discRow.classList.remove('d-none');
            document.getElementById('settleSummaryDiscountPercent').textContent = settleDiscountPercent;
            document.getElementById('settleSummaryDiscount').textContent = '-$' + discount.toLocaleString('es-CO');
            document.getElementById('settleDiscountAmountLabel').textContent = '-$' + discount.toLocaleString('es-CO');
        } else {
            discRow.classList.add('d-none');
            document.getElementById('settleDiscountAmountLabel').textContent = '$0';
        }
    }

    const tipRow = document.getElementById('settleSummaryTipRow');
    if (tipRow) {
        if (settleTipActive && tip > 0) {
            tipRow.classList.remove('d-none');
            document.getElementById('settleSummaryTip').textContent = '+$' + tip.toLocaleString('es-CO');
        } else {
            tipRow.classList.add('d-none');
        }
    }

    const taxRow = document.getElementById('settleSummaryTaxRow');
    if (taxRow) {
        if (settleTaxActive && tax > 0) {
            taxRow.classList.remove('d-none');
            document.getElementById('settleSummaryTax').textContent = '+$' + tax.toLocaleString('es-CO');
        } else {
            taxRow.classList.add('d-none');
        }
    }

    const totalEl = document.getElementById('settleModalTotal');
    if (totalEl) totalEl.textContent = '$' + ORDER_TOTAL.toLocaleString('es-CO');

    if (settlePaymentLines.length === 1) {
        settlePaymentLines[0].amount = ORDER_TOTAL;
        if (settlePaymentLines[0].method === 'cash') {
            settlePaymentLines[0].cash_received = ORDER_TOTAL;
        }
        renderSettlePaymentLines();
    } else {
        recalculateSettleSummary();
    }
}

function renderSettlePaymentLines() {
    const container = document.getElementById('settlePaymentLinesContainer');
    if (!container) return;
    container.innerHTML = '';

    const covered = settlePaymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const diff = ORDER_TOTAL - covered;

    settlePaymentLines.forEach((line, idx) => {
        const div = document.createElement('div');
        div.className = 'p-2.5 bg-white rounded-3 border shadow-sm';

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
                           oninput="updateSettleCashReceived(${idx}, this.value)" 
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
                       value="${escapeHtml(line.reference || '')}" 
                       oninput="updateSettleRef(${idx}, this.value)" 
                       title="Número de comprobante o aprobación del datáfono">
            `;
        } else if (line.method === 'transfer') {
            thirdFieldHtml = `
                <label class="form-label d-block text-muted mb-1 text-truncate" style="font-size: 0.7rem; font-weight: 600;" title="N° de Comprobante / Transacción Nequi o Bancolombia">
                    N° COMPROBANTE
                </label>
                <input type="text" class="form-control form-control-sm" 
                       placeholder="Ej. Nequi #9021" 
                       value="${escapeHtml(line.reference || '')}" 
                       oninput="updateSettleRef(${idx}, this.value)" 
                       title="Número de comprobante o referencia de transferencia">
            `;
        } else {
            thirdFieldHtml = `
                <label class="form-label d-block text-muted mb-1 text-truncate" style="font-size: 0.7rem; font-weight: 600;" title="N° de Soporte o Detalle del pago">
                    N° SOPORTE / DETALLE
                </label>
                <input type="text" class="form-control form-control-sm" 
                       placeholder="Ej. Bono / Vale #12" 
                       value="${escapeHtml(line.reference || '')}" 
                       oninput="updateSettleRef(${idx}, this.value)" 
                       title="Detalle o soporte del pago">
            `;
        }

        div.innerHTML = `
            <div class="row g-2 align-items-center">
                <div class="col-4">
                    <label class="form-label d-block text-muted mb-1" style="font-size: 0.7rem; font-weight: 600;">MÉTODO DE PAGO</label>
                    <select class="form-select form-select-sm fw-semibold" onchange="updateSettleMethod(${idx}, this.value)">
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
                        <input type="number" class="form-control text-end fw-bold text-dark settle-amount-input" 
                               data-idx="${idx}"
                               value="${line.amount}" step="0.01" min="0" placeholder="0" 
                               oninput="updateSettleAmount(${idx}, this.value)" 
                               title="Monto a cobrar con este método">
                    </div>
                </div>
                <div class="col-3">
                    ${thirdFieldHtml}
                </div>
                <div class="col-1 text-center pt-3">
                    ${settlePaymentLines.length > 1 ? `
                        <button type="button" class="btn btn-outline-danger btn-sm p-0 rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 26px; height: 26px;" onclick="removeSettleLine(${idx})" title="Eliminar método">
                            <i class="bi bi-trash" style="font-size: 0.75rem;"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
            ${diff > 0.01 && line.amount < ORDER_TOTAL ? `
                <div class="mt-1 pt-1 border-top border-light d-flex justify-content-end">
                    <button type="button" class="btn btn-link btn-xs p-0 text-success text-decoration-none fw-semibold" style="font-size: 0.72rem;" onclick="assignRemainingToSettleLine(${idx})">
                        <i class="bi bi-magic me-1"></i> Asignar restante ($${diff.toLocaleString('es-CO')}) a este método
                    </button>
                </div>
            ` : ''}
        `;
        container.appendChild(div);
    });

    const addBtn = document.getElementById('btnSettleAddPayment');
    if (addBtn) addBtn.disabled = settlePaymentLines.length >= 5;
    recalculateSettleSummary();
}

function addSettlePaymentLine() {
    if (settlePaymentLines.length >= 5) return;
    const covered = settlePaymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const remaining = Math.max(0, ORDER_TOTAL - covered);
    const hasCash = settlePaymentLines.some(p => p.method === 'cash');
    const newMethod = hasCash ? 'transfer' : 'cash';

    if (remaining > 0.01) {
        settlePaymentLines.push({
            method: newMethod,
            amount: remaining,
            cash_received: newMethod === 'cash' ? remaining : null,
            reference: ''
        });
    } else {
        if (settlePaymentLines.length === 1) {
            const half1 = Math.round(ORDER_TOTAL / 2);
            const half2 = ORDER_TOTAL - half1;
            settlePaymentLines[0].amount = half1;
            if (settlePaymentLines[0].method === 'cash') {
                settlePaymentLines[0].cash_received = half1;
            }
            settlePaymentLines.push({
                method: newMethod,
                amount: half2,
                cash_received: newMethod === 'cash' ? half2 : null,
                reference: ''
            });
        } else {
            settlePaymentLines.push({
                method: newMethod,
                amount: 0,
                cash_received: newMethod === 'cash' ? 0 : null,
                reference: ''
            });
        }
    }
    renderSettlePaymentLines();
}

function removeSettleLine(idx) {
    settlePaymentLines.splice(idx, 1);
    if (settlePaymentLines.length === 1) {
        settlePaymentLines[0].amount = ORDER_TOTAL;
        if (settlePaymentLines[0].method === 'cash') {
            settlePaymentLines[0].cash_received = ORDER_TOTAL;
        }
    }
    renderSettlePaymentLines();
}

function updateSettleMethod(idx, method) {
    if (method === 'cash' && settlePaymentLines.filter((p, i) => i !== idx && p.method === 'cash').length > 0) {
        califitNotify('Solo se permite una sola línea de pago en efectivo.', 'warning', 'Método de Pago');
        renderSettlePaymentLines();
        return;
    }
    settlePaymentLines[idx].method = method;
    settlePaymentLines[idx].cash_received = method === 'cash' ? settlePaymentLines[idx].amount : null;
    renderSettlePaymentLines();
}

function updateSettleAmount(idx, val) {
    const newAmt = parseFloat(window.unformatCOP ? window.unformatCOP(val) : val) || 0;
    settlePaymentLines[idx].amount = newAmt;

    if (settlePaymentLines[idx].method === 'cash') {
        if (!settlePaymentLines[idx].cash_received || settlePaymentLines[idx].cash_received < newAmt) {
            settlePaymentLines[idx].cash_received = newAmt;
        }
    }

    if (settlePaymentLines.length === 2) {
        const otherIdx = idx === 0 ? 1 : 0;
        const otherRemaining = Math.max(0, ORDER_TOTAL - newAmt);
        settlePaymentLines[otherIdx].amount = otherRemaining;
        if (settlePaymentLines[otherIdx].method === 'cash') {
            settlePaymentLines[otherIdx].cash_received = otherRemaining;
        }

        const inputs = document.querySelectorAll('.settle-amount-input');
        inputs.forEach(inp => {
            if (parseInt(inp.dataset.idx) === otherIdx) {
                inp.value = otherRemaining;
            }
        });
    }

    recalculateSettleSummary();
}

function assignRemainingToSettleLine(idx) {
    const otherCovered = settlePaymentLines.reduce((acc, p, i) => i !== idx ? acc + (parseFloat(p.amount) || 0) : acc, 0);
    const needed = Math.max(0, ORDER_TOTAL - otherCovered);
    settlePaymentLines[idx].amount = needed;
    if (settlePaymentLines[idx].method === 'cash') {
        settlePaymentLines[idx].cash_received = needed;
    }
    renderSettlePaymentLines();
}

function updateSettleCashReceived(idx, val) {
    settlePaymentLines[idx].cash_received = parseFloat(window.unformatCOP ? window.unformatCOP(val) : val) || 0;
    recalculateSettleSummary();
}

function updateSettleRef(idx, val) {
    settlePaymentLines[idx].reference = val;
}

function setSettleCashQuick(val) {
    const cashLine = settlePaymentLines.find(p => p.method === 'cash');
    if (!cashLine) return;
    if (val === 'exact') {
        cashLine.cash_received = cashLine.amount;
    } else {
        cashLine.cash_received = parseFloat(val);
    }
    renderSettlePaymentLines();
}

function recalculateSettleSummary() {
    const covered = settlePaymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const diff = ORDER_TOTAL - covered;

    const covEl = document.getElementById('settleModalCovered');
    if (covEl) covEl.textContent = '$' + covered.toLocaleString('es-CO');

    const diffEl = document.getElementById('settleModalDiff');
    if (diffEl) {
        if (Math.abs(diff) < 0.01) {
            diffEl.innerHTML = '<span class="fw-bold text-success"><i class="bi bi-check-circle me-1"></i>$0 (Exacto)</span>';
        } else if (diff > 0) {
            diffEl.innerHTML = '<span class="fw-bold text-danger"><i class="bi bi-exclamation-circle me-1"></i>Faltan $' + diff.toLocaleString('es-CO') + '</span>';
        } else {
            diffEl.innerHTML = '<span class="fw-bold text-primary"><i class="bi bi-info-circle me-1"></i>Excedente $' + Math.abs(diff).toLocaleString('es-CO') + '</span>';
        }
    }

    const cashLine = settlePaymentLines.find(p => p.method === 'cash');
    const changeBox = document.getElementById('settleChangeBox');
    const changeAmountEl = document.getElementById('settleChangeAmount');

    if (changeBox && changeAmountEl) {
        if (cashLine) {
            const received = parseFloat(cashLine.cash_received) || 0;
            const toPay = parseFloat(cashLine.amount) || 0;
            const change = received - toPay;

            if (change >= 0) {
                changeAmountEl.innerHTML = `
                    <div class="small fw-bold text-muted mb-1 text-uppercase">Cambio / Vuelto al Cliente</div>
                    <h4 class="fw-black mb-0 text-success font-monospace">$${change.toLocaleString('es-CO')}</h4>
                `;
                changeBox.style.backgroundColor = '#ecfdf5';
                changeBox.style.borderColor = '#a7f3d0';
            } else {
                changeAmountEl.innerHTML = `
                    <div class="small fw-bold text-danger mb-1 text-uppercase"><i class="bi bi-exclamation-triangle me-1"></i>Efectivo Insuficiente</div>
                    <h4 class="fw-black mb-0 text-danger font-monospace">Faltan $${Math.abs(change).toLocaleString('es-CO')}</h4>
                `;
                changeBox.style.backgroundColor = '#fef2f2';
                changeBox.style.borderColor = '#fca5a5';
            }
        } else {
            changeAmountEl.innerHTML = `
                <div class="small fw-bold text-muted mb-1 text-uppercase">Pago Electrónico</div>
                <h4 class="fw-black mb-0 text-secondary font-monospace">$0 <span class="fs-6 fw-normal">(Sin vuelto)</span></h4>
            `;
            changeBox.style.backgroundColor = '#f8fafc';
            changeBox.style.borderColor = '#e2e8f0';
        }
    }
}

function toggleSettleCreditMode(isCredit) {
    const creditFields = document.getElementById('settleCreditFields');
    const standardSection = document.getElementById('settleStandardPaymentsSection');
    const btn = document.getElementById('btnSubmitSettle');

    if (isCredit) {
        creditFields.classList.remove('d-none');
        standardSection.classList.add('d-none');
        btn.innerHTML = '<i class="bi bi-wallet2 fs-5"></i> <span class="fs-6">Confirmar Venta a Crédito (Despachar)</span>';
        onSettleCreditCustomerChange();
    } else {
        creditFields.classList.add('d-none');
        standardSection.classList.remove('d-none');
        btn.innerHTML = '<i class="bi bi-check2-circle fs-5"></i> <span class="fs-6">Registrar Venta y Liquidar</span>';
        recalculateSettleSummary();
    }
}

function onSettleCreditCustomerChange() {
    const sel = document.getElementById('settleCreditCustomerSelect');
    const err = document.getElementById('settleCreditCustomerError');
    const selectedOpt = sel.options[sel.selectedIndex];
    const phone = selectedOpt ? selectedOpt.dataset.phone : '';

    if (!sel.value || !phone || phone.trim() === '') {
        err.classList.remove('d-none');
    } else {
        err.classList.add('d-none');
    }
}

async function submitSettle() {
    const isCredit = document.getElementById('settleCreditSwitch').checked;
    const custSel = document.getElementById('settleCustomerSelect');
    const custOpt = custSel ? custSel.options[custSel.selectedIndex] : null;

    const discountAmount = Math.round(ORDER_PENDING_SUBTOTAL * (settleDiscountPercent / 100));
    const baseAfterDiscount = Math.max(0, ORDER_PENDING_SUBTOTAL - discountAmount);
    const taxAmount = settleTaxActive ? Math.round(baseAfterDiscount * 0.08) : 0;
    const tipAmount = settleTipActive ? settleTipAmount : 0;

    let payload = {
        customer_id: custSel && custSel.value ? parseInt(custSel.value) : null,
        customer_name: custOpt ? custOpt.dataset.name : 'Consumidor Final',
        customer_document: custOpt ? custOpt.dataset.doc : '222222222222',
        delivery_fee: ORDER_DELIVERY_FEE,
        discount_percentage: settleDiscountPercent,
        discount: discountAmount,
        tip: tipAmount,
        tax_inc: taxAmount,
        items: ORDER_ITEMS_DATA
    };

    if (isCredit) {
        const sel = document.getElementById('settleCreditCustomerSelect');
        const selectedOpt = sel.options[sel.selectedIndex];
        if (!sel.value) {
            califitNotify('Debe seleccionar un cliente registrado con teléfono para otorgar crédito.', 'warning', 'Cliente Obligatorio');
            return;
        }
        const phone = selectedOpt.dataset.phone;
        if (!phone || phone.trim() === '') {
            califitNotify('El cliente seleccionado no cuenta con teléfono registrado. Actualice sus datos antes de otorgar crédito.', 'danger', 'Validación Crédito');
            return;
        }

        payload.customer_id = parseInt(sel.value);
        payload.customer_name = selectedOpt.dataset.name;
        payload.customer_document = selectedOpt.dataset.doc;
        payload.is_credit = true;
        payload.credit_due_date = document.getElementById('settleCreditDueDate').value;
        payload.payments = [];
    } else {
        const covered = settlePaymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
        if (Math.abs(ORDER_TOTAL - covered) > 0.01) {
            califitNotify(`La suma de los pagos ($${covered.toLocaleString('es-CO')}) debe coincidir exactamente con el total de la comanda ($${ORDER_TOTAL.toLocaleString('es-CO')}).`, 'warning', 'Descuadre en Pagos');
            return;
        }

        const cashLine = settlePaymentLines.find(p => p.method === 'cash');
        if (cashLine && (parseFloat(cashLine.cash_received) || 0) < cashLine.amount) {
            califitNotify('El dinero recibido en efectivo no puede ser inferior al monto asignado en la línea de efectivo.', 'warning', 'Efectivo Insuficiente');
            return;
        }

        payload.is_credit = false;
        payload.payments = settlePaymentLines.map(p => ({
            method: p.method,
            amount: p.amount,
            reference: p.reference || null,
            cash_received: p.method === 'cash' ? p.cash_received : null
        }));
    }

    const btn = document.getElementById('btnSubmitSettle');
    const originalSettleBtnHtml = btn.dataset.originalHtml || btn.innerHTML;
    btn.dataset.originalHtml = originalSettleBtnHtml;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';

    try {
        const res = await fetch("{{ route('restaurant.orders.settle', $order) }}", {
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
            window.open(`/prints/sales/${data.sale_id}/receipt`, '_blank', 'width=340,height=620');
            window.location.href = "{{ route('restaurant.tables.index') }}";
        } else {
            califitNotify('Error al liquidar comanda: ' + (data.message || JSON.stringify(data.errors || data)), 'danger', 'Error al Liquidar');
        }
    } catch (err) {
        console.error(err);
        califitNotify('Ocurrió un error en el servidor al liquidar.', 'danger', 'Error de Conexión');
    } finally {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.originalHtml || '<i class="bi bi-check2-circle fs-5"></i> <span class="fs-6">Registrar Venta y Liquidar</span>';
    }
}

// ==========================================
// DIVISIÓN DE CUENTA POR PLATOS
// ==========================================
let splitDiscountPercent = 0;
let splitTipActive = false;
let splitTipAmount = 0;
let splitTaxActive = false;

let splitSubtotal = 0;
let splitDiscount = 0;
let splitTax = 0;
let splitTip = 0;
let splitFinalTotal = 0;

function openSplitBillModal() {
    splitDiscountPercent = 0;
    const discInp = document.getElementById('splitItemDiscountPercent');
    if (discInp) discInp.value = 0;
    splitTipActive = false;
    const tipSw = document.getElementById('splitItemTipSwitch');
    if (tipSw) tipSw.checked = false;
    const tipCont = document.getElementById('splitItemTipCustomContainer');
    if (tipCont) tipCont.classList.add('d-none');
    splitTaxActive = false;
    const taxSw = document.getElementById('splitItemTaxSwitch');
    if (taxSw) taxSw.checked = false;

    calculateItemSplitTotal();

    const modal = new bootstrap.Modal(document.getElementById('splitBillModal'));
    modal.show();
}

function toggleItemRowCheckbox(event, itemId) {
    if (event.target && (event.target.tagName === 'INPUT' || event.target.tagName === 'LABEL')) {
        return;
    }
    const cb = document.getElementById('split_item_' + itemId);
    if (cb && !cb.disabled) {
        cb.checked = !cb.checked;
        calculateItemSplitTotal();
    }
}

function toggleSelectAllUnpaidItems() {
    const checkboxes = document.querySelectorAll('.split-item-checkbox:not(:disabled)');
    if (!checkboxes.length) return;

    const anyUnchecked = Array.from(checkboxes).some(cb => !cb.checked);
    checkboxes.forEach(cb => cb.checked = anyUnchecked);

    const btn = document.getElementById('btnToggleSelectAll');
    if (btn) {
        btn.innerText = anyUnchecked ? 'Desmarcar todos' : 'Marcar todos';
    }
    calculateItemSplitTotal();
}

function changeSplitDiscount(delta) {
    let current = parseInt(document.getElementById('splitItemDiscountPercent').value) || 0;
    current = Math.max(0, Math.min(100, current + delta));
    document.getElementById('splitItemDiscountPercent').value = current;
    onSplitDiscountInput();
}

function onSplitDiscountInput() {
    const input = document.getElementById('splitItemDiscountPercent');
    splitDiscountPercent = Math.max(0, Math.min(100, parseFloat(input.value) || 0));
    input.value = splitDiscountPercent;
    calculateItemSplitTotal();
}

function toggleSplitTip(checked) {
    splitTipActive = checked;
    const container = document.getElementById('splitItemTipCustomContainer');
    if (checked) {
        if (container) container.classList.remove('d-none');
        splitTipAmount = Math.round(splitSubtotal * 0.10);
        const amtInput = document.getElementById('splitItemTipAmount');
        if (amtInput) amtInput.value = splitTipAmount;
    } else {
        if (container) container.classList.add('d-none');
        splitTipAmount = 0;
    }
    calculateItemSplitTotal();
}

function onSplitTipAmountChange() {
    const val = parseFloat(document.getElementById('splitItemTipAmount').value) || 0;
    splitTipAmount = Math.max(0, val);
    calculateItemSplitTotal();
}

function toggleSplitTax(checked) {
    splitTaxActive = checked;
    calculateItemSplitTotal();
}

function calculateItemSplitTotal() {
    const checkboxes = document.querySelectorAll('.split-item-checkbox:checked');
    let subtotal = 0;
    checkboxes.forEach(cb => {
        subtotal += parseFloat(cb.dataset.subtotal) || 0;
    });

    splitSubtotal = subtotal;

    const count = checkboxes.length;
    const countDisplay = document.getElementById('splitItemsCountDisplay');
    if (countDisplay) countDisplay.innerText = `${count} plato(s) / ítem(s) seleccionados`;
    const countBadge = document.getElementById('splitSummaryCountBadge');
    if (countBadge) countBadge.innerText = count;

    const subtotalEl = document.getElementById('splitSummarySubtotal');
    if (subtotalEl) subtotalEl.innerText = '$' + subtotal.toLocaleString('es-CO');

    splitDiscount = Math.round(subtotal * (splitDiscountPercent / 100));
    const discRow = document.getElementById('splitSummaryDiscountRow');
    if (discRow) {
        if (splitDiscount > 0) {
            discRow.classList.remove('d-none');
            document.getElementById('splitSummaryDiscountPercent').textContent = splitDiscountPercent;
            document.getElementById('splitSummaryDiscount').textContent = '-$' + splitDiscount.toLocaleString('es-CO');
        } else {
            discRow.classList.add('d-none');
        }
    }

    if (splitTipActive && splitTipAmount === 0 && subtotal > 0) {
        splitTipAmount = Math.round(subtotal * 0.10);
        const amtInp = document.getElementById('splitItemTipAmount');
        if (amtInp) amtInp.value = splitTipAmount;
    }
    splitTip = splitTipActive ? splitTipAmount : 0;
    const tipRow = document.getElementById('splitSummaryTipRow');
    if (tipRow) {
        if (splitTipActive && splitTip > 0) {
            tipRow.classList.remove('d-none');
            document.getElementById('splitSummaryTip').textContent = '+$' + splitTip.toLocaleString('es-CO');
        } else {
            tipRow.classList.add('d-none');
        }
    }

    const baseAfterDiscount = Math.max(0, subtotal - splitDiscount);
    splitTax = splitTaxActive ? Math.round(baseAfterDiscount * 0.08) : 0;
    const taxRow = document.getElementById('splitSummaryTaxRow');
    if (taxRow) {
        if (splitTaxActive && splitTax > 0) {
            taxRow.classList.remove('d-none');
            document.getElementById('splitSummaryTax').textContent = '+$' + splitTax.toLocaleString('es-CO');
        } else {
            taxRow.classList.add('d-none');
        }
    }

    splitFinalTotal = Math.max(0, Math.round(subtotal - splitDiscount + splitTax + splitTip));
    const totalEl = document.getElementById('splitItemsSelectedTotal');
    if (totalEl) totalEl.innerText = '$' + splitFinalTotal.toLocaleString('es-CO');

    const alertEl = document.getElementById('splitNoItemsAlert');
    const shortcutsEl = document.getElementById('splitCashShortcuts');
    const container = document.getElementById('splitPaymentLinesContainer');

    if (subtotal <= 0) {
        if (alertEl) alertEl.classList.remove('d-none');
        if (shortcutsEl) shortcutsEl.classList.add('d-none');
        if (container) container.innerHTML = '';
        splitPaymentLines = [];
        recalculateSplitPaymentSummary();
        return;
    } else {
        if (alertEl) alertEl.classList.add('d-none');
        if (shortcutsEl) shortcutsEl.classList.remove('d-none');
    }

    if (splitPaymentLines.length === 0) {
        splitPaymentLines = [{
            method: 'cash',
            amount: splitFinalTotal,
            cash_received: splitFinalTotal,
            reference: ''
        }];
        renderSplitPaymentLines();
    } else if (splitPaymentLines.length === 1) {
        splitPaymentLines[0].amount = splitFinalTotal;
        if (splitPaymentLines[0].method === 'cash') {
            splitPaymentLines[0].cash_received = splitFinalTotal;
        }
        renderSplitPaymentLines();
    } else {
        recalculateSplitPaymentSummary();
    }
}

function renderSplitPaymentLines() {
    const container = document.getElementById('splitPaymentLinesContainer');
    if (!container) return;
    container.innerHTML = '';

    const covered = splitPaymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const diff = splitFinalTotal - covered;

    splitPaymentLines.forEach((line, idx) => {
        const div = document.createElement('div');
        div.className = 'p-2.5 bg-white rounded-3 border shadow-xs mb-2';

        let thirdFieldHtml = '';
        if (line.method === 'cash') {
            thirdFieldHtml = `
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted fw-semibold">$</span>
                    <input type="number" class="form-control text-end fw-bold text-dark no-spinners" 
                           placeholder="Entregado" 
                           value="${line.cash_received !== null && line.cash_received !== undefined ? line.cash_received : ''}" 
                           oninput="updateSplitCashReceived(${idx}, this.value)" 
                           title="Efectivo entregado por el cliente">
                </div>
            `;
        } else {
            thirdFieldHtml = `
                <input type="text" class="form-control form-control-sm" 
                       placeholder="Voucher / Ref." 
                       value="${line.reference || ''}" 
                       oninput="updateSplitRef(${idx}, this.value)" 
                       title="Referencia o soporte de pago">
            `;
        }

        div.innerHTML = `
            <div class="row g-2 align-items-center">
                {{-- Columna 1: Método de Pago --}}
                <div class="col-12 col-sm-4">
                    <label class="form-label d-block text-muted mb-1 text-uppercase" style="font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em;">MÉTODO</label>
                    <select class="form-select form-select-sm fw-semibold" onchange="updateSplitMethod(${idx}, this.value)">
                        <option value="cash" ${line.method === 'cash' ? 'selected' : ''}>💵 Efectivo</option>
                        <option value="nequi" ${line.method === 'nequi' || (line.method === 'transfer' && (!line.reference || !line.reference.includes('Daviplata'))) ? 'selected' : ''}>📲 Nequi</option>
                        <option value="daviplata" ${line.method === 'daviplata' || (line.reference && line.reference.includes('Daviplata')) ? 'selected' : ''}>📱 Daviplata</option>
                        <option value="card" ${line.method === 'card' ? 'selected' : ''}>💳 Tarjeta</option>
                        <option value="transfer" ${line.method === 'transfer' && line.reference && !line.reference.includes('Nequi') && !line.reference.includes('Daviplata') ? 'selected' : ''}>📄 Transferencia</option>
                        <option value="other" ${line.method === 'other' ? 'selected' : ''}>📄 Otro</option>
                    </select>
                </div>

                {{-- Columna 2: Monto a Cobrar --}}
                <div class="col-6 col-sm-4">
                    <label class="form-label d-block text-muted mb-1 text-uppercase" style="font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em;">MONTO A COBRAR</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light fw-bold text-success">$</span>
                        <input type="number" class="form-control text-end fw-bold text-dark split-amount-input no-spinners" 
                               data-idx="${idx}"
                               value="${line.amount}" step="0.01" min="0" placeholder="0" 
                               oninput="updateSplitAmount(${idx}, this.value)" 
                               title="Monto a cobrar con este método">
                    </div>
                </div>

                {{-- Columna 3: Recibido / Soporte + Botón Eliminar --}}
                <div class="col-6 col-sm-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label text-muted mb-0 text-uppercase" style="font-size: 0.68rem; font-weight: 700; letter-spacing: 0.04em;">RECIBIDO / SOPORTE</label>
                        ${splitPaymentLines.length > 1 ? `
                            <button type="button" class="btn btn-outline-danger btn-xs p-0 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 22px; height: 22px;" onclick="removeSplitLine(${idx})" title="Eliminar método">
                                <i class="bi bi-trash" style="font-size: 0.68rem;"></i>
                            </button>
                        ` : ''}
                    </div>
                    ${thirdFieldHtml}
                </div>
            </div>
            ${diff > 0.01 && line.amount < splitFinalTotal ? `
                <div class="mt-1.5 pt-1 border-top border-light d-flex justify-content-end">
                    <button type="button" class="btn btn-link btn-xs p-0 text-success text-decoration-none fw-semibold" style="font-size: 0.72rem;" onclick="assignRemainingToSplitLine(${idx})">
                        <i class="bi bi-magic me-1"></i> Asignar restante ($${diff.toLocaleString('es-CO')})
                    </button>
                </div>
            ` : ''}
        `;
        container.appendChild(div);
    });

    const addBtn = document.getElementById('btnSplitAddPayment');
    if (addBtn) addBtn.disabled = splitPaymentLines.length >= 5;
    recalculateSplitPaymentSummary();
}

function addSplitPaymentLine() {
    if (splitPaymentLines.length >= 5) return;
    const covered = splitPaymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const remaining = Math.max(0, splitFinalTotal - covered);
    const hasCash = splitPaymentLines.some(p => p.method === 'cash');
    const newMethod = hasCash ? 'transfer' : 'cash';

    if (remaining > 0.01) {
        splitPaymentLines.push({
            method: newMethod,
            amount: remaining,
            cash_received: newMethod === 'cash' ? remaining : null,
            reference: ''
        });
    } else {
        if (splitPaymentLines.length === 1) {
            const half1 = Math.round(splitFinalTotal / 2);
            const half2 = splitFinalTotal - half1;
            splitPaymentLines[0].amount = half1;
            if (splitPaymentLines[0].method === 'cash') splitPaymentLines[0].cash_received = half1;
            splitPaymentLines.push({
                method: newMethod,
                amount: half2,
                cash_received: newMethod === 'cash' ? half2 : null,
                reference: ''
            });
        } else {
            splitPaymentLines.push({
                method: newMethod,
                amount: 0,
                cash_received: newMethod === 'cash' ? 0 : null,
                reference: ''
            });
        }
    }
    renderSplitPaymentLines();
}

function removeSplitLine(idx) {
    splitPaymentLines.splice(idx, 1);
    if (splitPaymentLines.length === 1) {
        splitPaymentLines[0].amount = splitFinalTotal;
        if (splitPaymentLines[0].method === 'cash') {
            splitPaymentLines[0].cash_received = splitFinalTotal;
        }
    }
    renderSplitPaymentLines();
}

function updateSplitMethod(idx, method) {
    if (method === 'cash' && splitPaymentLines.filter((p, i) => i !== idx && p.method === 'cash').length > 0) {
        califitNotify('Solo se permite una línea de pago en efectivo.', 'warning', 'Método');
        renderSplitPaymentLines();
        return;
    }
    splitPaymentLines[idx].method = method;
    splitPaymentLines[idx].cash_received = method === 'cash' ? splitPaymentLines[idx].amount : null;
    renderSplitPaymentLines();
}

function updateSplitAmount(idx, val) {
    const newAmt = parseFloat(window.unformatCOP ? window.unformatCOP(val) : val) || 0;
    splitPaymentLines[idx].amount = newAmt;

    if (splitPaymentLines[idx].method === 'cash') {
        if (!splitPaymentLines[idx].cash_received || splitPaymentLines[idx].cash_received < newAmt) {
            splitPaymentLines[idx].cash_received = newAmt;
        }
    }

    if (splitPaymentLines.length === 2) {
        const otherIdx = idx === 0 ? 1 : 0;
        const otherRemaining = Math.max(0, splitFinalTotal - newAmt);
        splitPaymentLines[otherIdx].amount = otherRemaining;
        if (splitPaymentLines[otherIdx].method === 'cash') {
            splitPaymentLines[otherIdx].cash_received = otherRemaining;
        }

        const inputs = document.querySelectorAll('.split-amount-input');
        inputs.forEach(inp => {
            if (parseInt(inp.dataset.idx) === otherIdx) {
                inp.value = otherRemaining;
            }
        });
    }

    recalculateSplitPaymentSummary();
}

function assignRemainingToSplitLine(idx) {
    const otherCovered = splitPaymentLines.reduce((acc, p, i) => i !== idx ? acc + (parseFloat(p.amount) || 0) : acc, 0);
    const needed = Math.max(0, splitFinalTotal - otherCovered);
    splitPaymentLines[idx].amount = needed;
    if (splitPaymentLines[idx].method === 'cash') {
        splitPaymentLines[idx].cash_received = needed;
    }
    renderSplitPaymentLines();
}

function updateSplitCashReceived(idx, val) {
    splitPaymentLines[idx].cash_received = parseFloat(window.unformatCOP ? window.unformatCOP(val) : val) || 0;
    recalculateSplitPaymentSummary();
}

function updateSplitRef(idx, val) {
    splitPaymentLines[idx].reference = val;
}

function setSplitCashQuick(val) {
    const cashLine = splitPaymentLines.find(p => p.method === 'cash');
    if (!cashLine) return;
    if (val === 'exact') {
        cashLine.cash_received = cashLine.amount;
    } else {
        cashLine.cash_received = parseFloat(val);
    }
    renderSplitPaymentLines();
}

function recalculateSplitPaymentSummary() {
    const covered = splitPaymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    const covText = document.getElementById('splitCoveredText');
    if (covText) {
        covText.textContent = '$' + covered.toLocaleString('es-CO');
    }
    const badge = document.getElementById('splitCoveredBadge');
    if (badge) {
        const diff = splitFinalTotal - covered;
        if (Math.abs(diff) < 0.01) {
            badge.className = 'badge bg-success-subtle text-success border border-success font-monospace px-2 py-1';
            badge.textContent = `Cubierto: $${covered.toLocaleString('es-CO')} (Exacto)`;
        } else if (diff > 0) {
            badge.className = 'badge bg-danger-subtle text-danger border border-danger font-monospace px-2 py-1';
            badge.textContent = `Faltan: $${diff.toLocaleString('es-CO')}`;
        } else {
            badge.className = 'badge bg-primary-subtle text-primary border border-primary font-monospace px-2 py-1';
            badge.textContent = `Excedente: $${Math.abs(diff).toLocaleString('es-CO')}`;
        }
    }

    const cashLine = splitPaymentLines.find(p => p.method === 'cash');
    const changeBox = document.getElementById('splitChangeBox');
    const changeDisplay = document.getElementById('splitChangeDisplay');

    if (!changeBox || !changeDisplay) return;

    if (cashLine) {
        const received = parseFloat(cashLine.cash_received) || 0;
        const toPay = parseFloat(cashLine.amount) || 0;
        const change = received - toPay;

        if (change >= 0) {
            changeDisplay.textContent = '$' + change.toLocaleString('es-CO');
            changeDisplay.className = 'fw-black mb-0 text-success font-monospace';
            changeBox.style.backgroundColor = '#ecfdf5';
            changeBox.style.borderColor = '#a7f3d0';
        } else {
            changeDisplay.textContent = 'Faltan $' + Math.abs(change).toLocaleString('es-CO');
            changeDisplay.className = 'fw-black mb-0 text-danger font-monospace';
            changeBox.style.backgroundColor = '#fef2f2';
            changeBox.style.borderColor = '#fca5a5';
        }
    } else {
        changeDisplay.textContent = '$0 (Sin vuelto)';
        changeDisplay.className = 'fw-black mb-0 text-secondary font-monospace';
        changeBox.style.backgroundColor = '#f8fafc';
        changeBox.style.borderColor = '#e2e8f0';
    }
}

function printPartialPreBill() {
    const checkboxes = document.querySelectorAll('.split-item-checkbox:checked');
    if (!checkboxes.length) {
        califitNotify('Debe seleccionar al menos un plato pendiente para imprimir la pre-cuenta individual.', 'warning', 'Platos Requeridos');
        return;
    }

    const itemIds = Array.from(checkboxes).map(cb => cb.value);
    const url = "{{ route('restaurant.orders.pre-bill-partial', $order) }}?items=" + itemIds.join(',');
    window.open(url, '_blank', 'width=340,height=620');
}

async function submitItemSplit() {
    const checkboxes = document.querySelectorAll('.split-item-checkbox:checked');
    if (!checkboxes.length || splitSubtotal <= 0) {
        califitNotify('Debe seleccionar al menos un plato pendiente en la columna izquierda para liquidar.', 'warning', 'Platos Requeridos');
        return;
    }

    const covered = splitPaymentLines.reduce((acc, p) => acc + (parseFloat(p.amount) || 0), 0);
    if (Math.abs(splitFinalTotal - covered) > 0.01) {
        califitNotify(`La suma de los métodos de pago ($${covered.toLocaleString('es-CO')}) debe coincidir exactamente con el Total a Liquidar ($${splitFinalTotal.toLocaleString('es-CO')}).`, 'warning', 'Descuadre en Pagos');
        return;
    }

    const cashLine = splitPaymentLines.find(p => p.method === 'cash');
    if (cashLine && (parseFloat(cashLine.cash_received) || 0) < cashLine.amount) {
        califitNotify('El efectivo entregado no puede ser menor al monto asignado en la línea de efectivo.', 'warning', 'Efectivo Insuficiente');
        return;
    }

    const itemIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    const custSel = document.getElementById('splitItemCustomerSelect');
    const custOpt = custSel ? custSel.options[custSel.selectedIndex] : null;

    const payload = {
        split_type: 'items',
        item_ids: itemIds,
        customer_id: custSel && custSel.value ? parseInt(custSel.value) : null,
        customer_name: custOpt ? custOpt.dataset.name : 'Consumidor Final',
        customer_document: custOpt ? custOpt.dataset.doc : '222222222222',
        discount_percentage: splitDiscountPercent,
        discount: splitDiscount,
        tip: splitTip,
        tax_inc: splitTax,
        notes: document.getElementById('splitItemNotes').value || null,
        payments: splitPaymentLines.map(p => {
            let method = p.method;
            let ref = p.reference ? p.reference.trim() : null;
            if (method === 'nequi') {
                method = 'transfer';
                ref = ref ? `Nequi: ${ref}` : 'Nequi';
            } else if (method === 'daviplata') {
                method = 'transfer';
                ref = ref ? `Daviplata: ${ref}` : 'Daviplata';
            }
            return {
                method: method,
                amount: p.amount,
                reference: ref,
                cash_received: method === 'cash' ? p.cash_received : null
            };
        })
    };

    const btn = document.getElementById('btnSubmitItemSplit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Cobrando platos...';

    try {
        const res = await fetch("{{ route('restaurant.orders.split-settle', $order) }}", {
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
            window.open(`/prints/sales/${data.sale_id}/receipt`, '_blank', 'width=340,height=620');
            if (data.is_order_closed) {
                califitNotify('Todos los platos han sido liquidados. La mesa ha sido liberada exitosamente.', 'success', 'Comanda Finalizada');
                setTimeout(() => window.location.href = "{{ route('restaurant.tables.index') }}", 1200);
            } else {
                location.reload();
            }
        } else {
            califitNotify('Error al cobrar platos: ' + (data.message || JSON.stringify(data.errors || data)), 'danger', 'Error de Cobro');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cash-stack fs-5"></i> <span>Cobrar Platos e Imprimir Factura</span>';
        }
    } catch (err) {
        console.error(err);
        califitNotify('Ocurrió un error al liquidar los platos.', 'danger', 'Error de Servidor');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cash-stack fs-5"></i> <span>Cobrar Platos e Imprimir Factura</span>';
    }
}

// Abrir modal de cobro automáticamente si se ingresa con ancla #cobro
document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#cobro') {
        openSettlementModal();
    }

    const settleModalEl = document.getElementById('settlementModal');
    if (settleModalEl) {
        settleModalEl.addEventListener('show.bs.modal', () => {
            const creditSw = document.getElementById('settleCreditSwitch');
            if (creditSw) {
                creditSw.checked = false;
                toggleSettleCreditMode(false);
            }
        });
        settleModalEl.addEventListener('hidden.bs.modal', () => {
            const creditSw = document.getElementById('settleCreditSwitch');
            if (creditSw) {
                creditSw.checked = false;
                toggleSettleCreditMode(false);
            }
        });
    }
});

function filterCustomerSelectBox(query, selectId) {
    const select = document.getElementById(selectId);
    if (!select) return;
    const q = (query || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").trim();
    let firstMatched = -1;
    for (let i = 0; i < select.options.length; i++) {
        const opt = select.options[i];
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
        select.selectedIndex = firstMatched;
        select.dispatchEvent(new Event('change'));
    }
}

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
