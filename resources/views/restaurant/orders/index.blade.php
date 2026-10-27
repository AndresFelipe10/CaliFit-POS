@extends('layouts.app')

@section('title', 'Comandas - Operación y Atención')

@section('content')
@php
    $tabParam = request('tab', '');
    $isHistoryActive = in_array($tabParam, ['history', 'past'], true) || request()->has('date_from') || request()->has('date_to') || request()->has('past_page') || request()->has('page');
    $isTodayActive = !$isHistoryActive;
    $activeTab = $isHistoryActive ? 'history' : 'today';
    $hasFilters = request()->has('search') || request()->has('channel') || request()->has('status') || request()->has('date_from') || request()->has('date_to') || request()->has('past_page') || request()->has('page');
    $todayFormatted = \Carbon\Carbon::parse($todayDate)->format('d/m/Y');
@endphp

<div class="container-fluid py-3 px-3 px-md-4">
    {{-- CABECERA DEL MÓDULO --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px; background-color: #ECFDF5; border: 1px solid #A7F3D0;">
                <i class="bi bi-receipt-cutoff fs-4" style="color: #10B981;"></i>
            </div>
            <div>
                <h1 class="h3 fw-bold mb-0 text-dark" style="letter-spacing: -0.02em;">Comandas</h1>
                <p class="text-muted small mb-0">Listado y seguimiento de pedidos de salón, cocina y despachos.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-success d-inline-flex align-items-center gap-2 fw-semibold px-3 py-2 rounded-pill shadow-sm" style="border-color: #10B981; color: #059669; min-height: 42px;">
                <i class="bi bi-grid-3x3-gap fs-5"></i>
                <span>Mapa de Mesas</span>
            </a>
        </div>
    </div>

    {{-- BARRA DE FILTROS Y BÚSQUEDA UNIFICADA --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('restaurant.orders.index') }}" class="row g-2 align-items-end" id="filtersForm">
                <input type="hidden" name="tab" value="{{ $activeTab }}" id="activeTabInput">

                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Búsqueda Rápida</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" 
                               name="search" 
                               class="form-control form-control-sm border-start-0 ps-0" 
                               placeholder="Folio #ORD, cliente, mesero..." 
                               value="{{ request('search') }}">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Canal</label>
                    <select name="channel" class="form-select form-select-sm">
                        <option value="">Todos los canales</option>
                        <option value="table" {{ request('channel') === 'table' ? 'selected' : '' }}>🍽️ Salón / Mesa</option>
                        <option value="delivery" {{ request('channel') === 'delivery' ? 'selected' : '' }}>🛵 Domicilio</option>
                        <option value="takeout" {{ request('channel') === 'takeout' ? 'selected' : '' }}>🛍️ Para Llevar</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Estado</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>🟡 En Cocina</option>
                        <option value="billed" {{ request('status') === 'billed' ? 'selected' : '' }}>🟡 En Cobro / Pre-cuenta</option>
                        <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>🟢 Cerrada / Pagada</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>🔴 Anulada (Auditoría)</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.05em;">Fecha (Historial)</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}" title="Desde">
                </div>

                <div class="col-6 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-califit btn-sm flex-grow-1 fw-semibold py-1.5">
                        <i class="bi bi-funnel me-1"></i> Filtrar
                    </button>
                    @if($hasFilters)
                        <a href="{{ route('restaurant.orders.index', ['tab' => $activeTab]) }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center justify-content-center" title="Limpiar filtros" style="width: 36px;">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- SELECTOR DE PESTAÑAS TIPO PÍLDORA ANCHA (SEGMENTED CONTROL) --}}
    <div class="d-flex justify-content-start mb-3">
        <div class="p-1.5 rounded-pill bg-light border shadow-sm d-inline-flex gap-1" role="tablist">
            <button class="btn btn-sm rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2 border-0 transition-all tab-switcher-btn {{ $isTodayActive ? 'active-tab' : 'inactive-tab' }}" 
                    id="today-tab" 
                    type="button" 
                    role="tab"
                    onclick="switchOrdersTab('today')"
                    style="{{ $isTodayActive ? 'background-color: #10B981; color: #FFFFFF;' : 'background-color: transparent; color: #4B5563;' }}">
                <i class="bi bi-calendar-check"></i>
                <span>Comandas Hoy ({{ $todayFormatted }})</span>
                <span class="badge rounded-pill px-2 py-0.5 ms-1" id="today-count-badge" style="{{ $isTodayActive ? 'background-color: rgba(255,255,255,0.25); color: #FFFFFF;' : 'background-color: #10B981; color: #FFFFFF;' }}">
                    {{ $todayCount }}
                </span>
            </button>

            <button class="btn btn-sm rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2 border-0 transition-all tab-switcher-btn {{ $isHistoryActive ? 'active-tab' : 'inactive-tab' }}" 
                    id="past-tab" 
                    type="button" 
                    role="tab"
                    onclick="switchOrdersTab('history')"
                    style="{{ $isHistoryActive ? 'background-color: #10B981; color: #FFFFFF;' : 'background-color: transparent; color: #4B5563;' }}">
                <i class="bi bi-clock-history"></i>
                <span>Historial Anterior</span>
                <span class="badge rounded-pill px-2 py-0.5 ms-1" id="history-count-badge" style="{{ $isHistoryActive ? 'background-color: rgba(255,255,255,0.25); color: #FFFFFF;' : 'background-color: #6B7280; color: #FFFFFF;' }}">
                    {{ $pastCount }}
                </span>
            </button>
        </div>
    </div>

    {{-- CONTENEDOR DE PESTAÑAS --}}
    <div class="tab-content" id="ordersTabContent">
        {{-- PESTAÑA 1: COMANDAS DE HOY --}}
        <div class="tab-pane fade {{ $isTodayActive ? 'show active' : '' }}" id="today-orders-pane" role="tabpanel" aria-labelledby="today-tab" tabindex="0">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-body p-0">
                    @if($todayOrders->isEmpty())
                        <div class="text-center py-5 px-3">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; background-color: #F3F4F6;">
                                <i class="bi bi-calendar4-event text-secondary display-5"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-1">No hay comandas registradas hoy</h4>
                            <p class="text-muted mb-4" style="max-width: 460px; margin: 0 auto;">
                                Abre una comanda desde una mesa del salón o registra un domicilio para comenzar la jornada.
                            </p>
                            <div class="d-flex flex-wrap justify-content-center gap-2">
                                <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-success fw-bold px-4 py-2 rounded-pill shadow-sm d-inline-flex align-items-center gap-2" style="border-color: #10B981; color: #059669;">
                                    <i class="bi bi-grid-3x3-gap"></i>
                                    <span>Ir al Salón</span>
                                </a>
                                <a href="{{ route('restaurant.dispatch.index') }}" class="btn btn-califit fw-bold px-4 py-2 rounded-pill shadow-sm d-inline-flex align-items-center gap-2">
                                    <i class="bi bi-bicycle"></i>
                                    <span>Nuevo Domicilio</span>
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead style="background-color: #F9FAFB; border-bottom: 1px solid #E5E7EB;">
                                    <tr>
                                        <th class="ps-4 py-3 text-uppercase small fw-bold text-muted" style="letter-spacing: 0.05em;">Folio / Hora</th>
                                        <th class="py-3 text-uppercase small fw-bold text-muted" style="letter-spacing: 0.05em;">Canal / Ubicación</th>
                                        <th class="py-3 text-uppercase small fw-bold text-muted" style="letter-spacing: 0.05em;">Mesero y Cliente</th>
                                        <th class="py-3 text-uppercase small fw-bold text-muted" style="letter-spacing: 0.05em;">Platos / Resumen</th>
                                        <th class="py-3 text-uppercase small fw-bold text-muted text-end" style="letter-spacing: 0.05em;">Total</th>
                                        <th class="py-3 text-uppercase small fw-bold text-muted" style="letter-spacing: 0.05em;">Estado & Auditoría</th>
                                        <th class="pe-4 py-3 text-uppercase small fw-bold text-muted text-end" style="letter-spacing: 0.05em;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($todayOrders as $order)
                                        <tr style="border-bottom: 1px solid #F3F4F6;">
                                            <td class="ps-4 py-3">
                                                <div class="fw-bold font-monospace text-dark fs-6">{{ $order->order_number }}</div>
                                                <div class="small text-muted">
                                                    <i class="bi bi-clock me-1"></i>{{ $order->created_at->format('H:i') }}
                                                    <span class="text-secondary opacity-75">({{ $order->created_at->diffForHumans() }})</span>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                @if($order->table)
                                                    <span class="badge rounded-pill px-3 py-1.5 border" style="background-color: #ECFDF5; color: #065F46; border-color: #A7F3D0 !important;">
                                                        <i class="bi bi-grid-fill me-1"></i>{{ $order->table->name }}
                                                    </span>
                                                @elseif($order->order_type === 'delivery')
                                                    <span class="badge rounded-pill px-3 py-1.5 border" style="background-color: #EFF6FF; color: #1E40AF; border-color: #BFDBFE !important;">
                                                        <i class="bi bi-bicycle me-1"></i>Domicilio
                                                    </span>
                                                @elseif($order->order_type === 'takeout')
                                                    <span class="badge rounded-pill px-3 py-1.5 border" style="background-color: #F5F3FF; color: #5B21B6; border-color: #DDD6FE !important;">
                                                        <i class="bi bi-bag-check me-1"></i>Para Llevar
                                                    </span>
                                                @else
                                                    <span class="badge rounded-pill px-3 py-1.5 bg-light text-dark border">
                                                        <i class="bi bi-shop me-1"></i>Salón
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-3">
                                                <div class="fw-bold text-dark mb-0.5">
                                                    <i class="bi bi-person me-1 text-muted"></i>{{ $order->customer_name ?? ($order->customer->name ?? 'Consumidor Final') }}
                                                </div>
                                                <div class="small text-muted">
                                                    Atiende: <span class="fw-semibold text-secondary">{{ $order->waiter_name ?: ($order->user->name ?? 'Personal') }}</span>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <div class="d-flex align-items-center gap-1.5">
                                                    <span class="badge bg-light text-dark border fw-bold">
                                                        {{ $order->items->sum('quantity') }} ítems
                                                    </span>
                                                    @if($order->items->isNotEmpty())
                                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 220px;" title="{{ $order->items->pluck('product.nombre')->filter()->join(', ') }}">
                                                            {{ $order->items->first()?->product?->nombre }}
                                                            @if($order->items->count() > 1)
                                                                <span class="text-secondary">+{{ $order->items->count() - 1 }} más</span>
                                                            @endif
                                                        </small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 text-end">
                                                <div class="fw-bold fs-6 text-dark">${{ number_format($order->total, 0, ',', '.') }}</div>
                                                @if($order->tip > 0)
                                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Propina: ${{ number_format($order->tip, 0, ',', '.') }}</small>
                                                @endif
                                            </td>
                                            <td class="py-3">
                                                @if(in_array($order->status, ['open', 'in_kitchen']))
                                                    <span class="badge rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="background-color: #F59E0B; color: #111827; font-size: 0.8rem;">
                                                        En Cocina
                                                    </span>
                                                @elseif($order->status === 'billed')
                                                    <span class="badge rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="background-color: #F59E0B; color: #111827; font-size: 0.8rem;">
                                                        En Cobro / Pre-cuenta emitida
                                                    </span>
                                                @elseif($order->status === 'closed')
                                                    <div>
                                                        <span class="badge rounded-pill px-3 py-1.5 text-white fw-bold shadow-sm" style="background-color: #059669; font-size: 0.8rem;">
                                                            Cerrada / Pagada
                                                        </span>
                                                        @if($order->sale)
                                                            <small class="d-block text-muted font-monospace mt-1" style="font-size: 0.72rem;">#FAC-{{ sprintf('%06d', $order->sale->id) }}</small>
                                                        @endif
                                                    </div>
                                                @elseif($order->status === 'cancelled')
                                                    <div class="d-flex flex-column gap-1" style="max-width: 280px;">
                                                        <div>
                                                            <span class="badge rounded-pill px-3 py-1.5 text-white fw-bold shadow-sm" style="background-color: #DC2626; font-size: 0.8rem;">
                                                                Anulada
                                                            </span>
                                                        </div>
                                                        <div class="p-1.5 rounded-2 bg-danger-subtle border border-danger-subtle">
                                                            <div class="d-flex align-items-center gap-1 text-danger fw-bold" style="font-size: 0.72rem;">
                                                                <i class="bi bi-shield-x"></i> Cancelada (Auditoría)
                                                            </div>
                                                            <div class="small text-danger-emphasis fst-italic text-truncate mt-0.5" title="{{ $order->cancellation_reason }}">
                                                                "{{ $order->cancellation_reason ?: 'Sin motivo registrado' }}"
                                                            </div>
                                                            <div class="text-muted" style="font-size: 0.7rem;">
                                                                Por: {{ $order->cancelledByUser->name ?? ($order->waiter_name ?: 'Operador') }}
                                                                @if($order->cancelled_at)
                                                                    &bull; {{ $order->cancelled_at->format('H:i') }}
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="badge bg-light text-dark border">{{ $order->status_label ?? $order->status }}</span>
                                                @endif
                                            </td>
                                            <td class="pe-4 py-3 text-end">
                                                <a href="{{ route('restaurant.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm">
                                                    <i class="bi bi-eye"></i> Detalle
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- PESTAÑA 2: HISTORIAL DE DÍAS ANTERIORES --}}
        <div class="tab-pane fade {{ $isHistoryActive ? 'show active' : '' }}" id="past-orders-pane" role="tabpanel" aria-labelledby="past-tab" tabindex="0">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                <div class="card-body p-0">
                    @if($pastOrders->isEmpty())
                        <div class="text-center py-5 px-3">
                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; background-color: #F3F4F6;">
                                <i class="bi bi-clock-history text-secondary display-5"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-1">No se encontraron comandas anteriores</h4>
                            <p class="text-muted mb-0" style="max-width: 460px; margin: 0 auto;">
                                No hay registros con los criterios de fecha y filtros seleccionados.
                            </p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead style="background-color: #F9FAFB; border-bottom: 1px solid #E5E7EB;">
                                    <tr>
                                        <th class="ps-4 py-3 text-uppercase small fw-bold text-muted" style="letter-spacing: 0.05em;">Folio / Fecha</th>
                                        <th class="py-3 text-uppercase small fw-bold text-muted" style="letter-spacing: 0.05em;">Canal / Ubicación</th>
                                        <th class="py-3 text-uppercase small fw-bold text-muted" style="letter-spacing: 0.05em;">Mesero y Cliente</th>
                                        <th class="py-3 text-uppercase small fw-bold text-muted" style="letter-spacing: 0.05em;">Platos / Resumen</th>
                                        <th class="py-3 text-uppercase small fw-bold text-muted text-end" style="letter-spacing: 0.05em;">Total</th>
                                        <th class="py-3 text-uppercase small fw-bold text-muted" style="letter-spacing: 0.05em;">Estado & Auditoría</th>
                                        <th class="pe-4 py-3 text-uppercase small fw-bold text-muted text-end" style="letter-spacing: 0.05em;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pastOrders as $order)
                                        <tr style="border-bottom: 1px solid #F3F4F6;">
                                            <td class="ps-4 py-3">
                                                <div class="fw-bold font-monospace text-dark fs-6">{{ $order->order_number }}</div>
                                                <div class="small text-muted">
                                                    <i class="bi bi-calendar3 me-1"></i>{{ $order->created_at->format('d/m/Y H:i') }}
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                @if($order->table)
                                                    <span class="badge rounded-pill px-3 py-1.5 border" style="background-color: #ECFDF5; color: #065F46; border-color: #A7F3D0 !important;">
                                                        <i class="bi bi-grid-fill me-1"></i>{{ $order->table->name }}
                                                    </span>
                                                @elseif($order->order_type === 'delivery')
                                                    <span class="badge rounded-pill px-3 py-1.5 border" style="background-color: #EFF6FF; color: #1E40AF; border-color: #BFDBFE !important;">
                                                        <i class="bi bi-bicycle me-1"></i>Domicilio
                                                    </span>
                                                @elseif($order->order_type === 'takeout')
                                                    <span class="badge rounded-pill px-3 py-1.5 border" style="background-color: #F5F3FF; color: #5B21B6; border-color: #DDD6FE !important;">
                                                        <i class="bi bi-bag-check me-1"></i>Para Llevar
                                                    </span>
                                                @else
                                                    <span class="badge rounded-pill px-3 py-1.5 bg-light text-dark border">
                                                        <i class="bi bi-shop me-1"></i>Salón
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-3">
                                                <div class="fw-bold text-dark mb-0.5">
                                                    <i class="bi bi-person me-1 text-muted"></i>{{ $order->customer_name ?? ($order->customer->name ?? 'Consumidor Final') }}
                                                </div>
                                                <div class="small text-muted">
                                                    Atendió: <span class="fw-semibold text-secondary">{{ $order->waiter_name ?: ($order->user->name ?? 'Personal') }}</span>
                                                </div>
                                            </td>
                                            <td class="py-3">
                                                <div class="d-flex align-items-center gap-1.5">
                                                    <span class="badge bg-light text-dark border fw-bold">
                                                        {{ $order->items->sum('quantity') }} ítems
                                                    </span>
                                                    @if($order->items->isNotEmpty())
                                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 220px;" title="{{ $order->items->pluck('product.nombre')->filter()->join(', ') }}">
                                                            {{ $order->items->first()?->product?->nombre }}
                                                            @if($order->items->count() > 1)
                                                                <span class="text-secondary">+{{ $order->items->count() - 1 }} más</span>
                                                            @endif
                                                        </small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 text-end">
                                                <div class="fw-bold fs-6 text-dark">${{ number_format($order->total, 0, ',', '.') }}</div>
                                            </td>
                                            <td class="py-3">
                                                @if(in_array($order->status, ['open', 'in_kitchen']))
                                                    <span class="badge rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="background-color: #F59E0B; color: #111827; font-size: 0.8rem;">
                                                        En Cocina
                                                    </span>
                                                @elseif($order->status === 'billed')
                                                    <span class="badge rounded-pill px-3 py-1.5 fw-bold shadow-sm" style="background-color: #F59E0B; color: #111827; font-size: 0.8rem;">
                                                        En Cobro / Pre-cuenta emitida
                                                    </span>
                                                @elseif($order->status === 'closed')
                                                    <div>
                                                        <span class="badge rounded-pill px-3 py-1.5 text-white fw-bold shadow-sm" style="background-color: #059669; font-size: 0.8rem;">
                                                            Cerrada / Pagada
                                                        </span>
                                                        @if($order->sale)
                                                            <small class="d-block text-muted font-monospace mt-1" style="font-size: 0.72rem;">#FAC-{{ sprintf('%06d', $order->sale->id) }}</small>
                                                        @endif
                                                    </div>
                                                @elseif($order->status === 'cancelled')
                                                    <div class="d-flex flex-column gap-1" style="max-width: 280px;">
                                                        <div>
                                                            <span class="badge rounded-pill px-3 py-1.5 text-white fw-bold shadow-sm" style="background-color: #DC2626; font-size: 0.8rem;">
                                                                Anulada
                                                            </span>
                                                        </div>
                                                        <div class="p-1.5 rounded-2 bg-danger-subtle border border-danger-subtle">
                                                            <div class="d-flex align-items-center gap-1 text-danger fw-bold" style="font-size: 0.72rem;">
                                                                <i class="bi bi-shield-x"></i> Cancelada (Auditoría)
                                                            </div>
                                                            <div class="small text-danger-emphasis fst-italic text-truncate mt-0.5" title="{{ $order->cancellation_reason }}">
                                                                "{{ $order->cancellation_reason ?: 'Sin motivo registrado' }}"
                                                            </div>
                                                            <div class="text-muted" style="font-size: 0.7rem;">
                                                                Por: {{ $order->cancelledByUser->name ?? ($order->waiter_name ?: 'Operador') }}
                                                                @if($order->cancelled_at)
                                                                    &bull; {{ $order->cancelled_at->format('d/m/Y H:i') }}
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="badge bg-light text-dark border">{{ $order->status_label ?? $order->status }}</span>
                                                @endif
                                            </td>
                                            <td class="pe-4 py-3 text-end">
                                                <a href="{{ route('restaurant.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1.5 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm">
                                                    <i class="bi bi-eye"></i> Detalle
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($pastOrders->hasPages())
                            <div class="p-3 border-top d-flex justify-content-center">
                                {{ $pastOrders->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function switchOrdersTab(tab) {
    const todayTab = document.getElementById('today-tab');
    const pastTab = document.getElementById('past-tab');
    const todayPane = document.getElementById('today-orders-pane');
    const pastPane = document.getElementById('past-orders-pane');
    const activeTabInput = document.getElementById('activeTabInput');
    const todayBadge = document.getElementById('today-count-badge');
    const historyBadge = document.getElementById('history-count-badge');

    if (tab === 'today') {
        if (todayTab) {
            todayTab.style.backgroundColor = '#10B981';
            todayTab.style.color = '#FFFFFF';
            todayTab.classList.add('active-tab', 'text-white', 'shadow-sm');
            todayTab.classList.remove('inactive-tab', 'text-secondary');
        }
        if (pastTab) {
            pastTab.style.backgroundColor = 'transparent';
            pastTab.style.color = '#4B5563';
            pastTab.classList.remove('active-tab', 'text-white', 'shadow-sm');
            pastTab.classList.add('inactive-tab', 'text-secondary');
        }
        if (todayBadge) {
            todayBadge.style.backgroundColor = 'rgba(255,255,255,0.25)';
            todayBadge.style.color = '#FFFFFF';
        }
        if (historyBadge) {
            historyBadge.style.backgroundColor = '#6B7280';
            historyBadge.style.color = '#FFFFFF';
        }
        if (todayPane) todayPane.classList.add('show', 'active');
        if (pastPane) pastPane.classList.remove('show', 'active');
        if (activeTabInput) activeTabInput.value = 'today';
    } else {
        if (pastTab) {
            pastTab.style.backgroundColor = '#10B981';
            pastTab.style.color = '#FFFFFF';
            pastTab.classList.add('active-tab', 'text-white', 'shadow-sm');
            pastTab.classList.remove('inactive-tab', 'text-secondary');
        }
        if (todayTab) {
            todayTab.style.backgroundColor = 'transparent';
            todayTab.style.color = '#4B5563';
            todayTab.classList.remove('active-tab', 'text-white', 'shadow-sm');
            todayTab.classList.add('inactive-tab', 'text-secondary');
        }
        if (historyBadge) {
            historyBadge.style.backgroundColor = 'rgba(255,255,255,0.25)';
            historyBadge.style.color = '#FFFFFF';
        }
        if (todayBadge) {
            todayBadge.style.backgroundColor = '#10B981';
            todayBadge.style.color = '#FFFFFF';
        }
        if (pastPane) pastPane.classList.add('show', 'active');
        if (todayPane) todayPane.classList.remove('show', 'active');
        if (activeTabInput) activeTabInput.value = 'history';
    }

    try {
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    } catch (e) {}
}
</script>
@endpush
@endsection
