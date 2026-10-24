@extends('layouts.app')

@section('title', 'Salón y Mapa de Mesas')

@section('content')
<div class="container-fluid py-3">
    {{-- Cabecera Responsiva Mobile POS --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div class="w-100 w-md-auto">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-grid-3x3-gap-fill text-primary fs-4"></i>
                <h1 class="fs-3 fw-bold text-dark mb-0">Salón y Mapa de Mesas</h1>
            </div>
            <p class="text-muted small mb-0">Gestión de mesas en tiempo real y asignación rápida de comandas.</p>
        </div>
        <div class="d-flex align-items-center gap-2 w-100 w-md-auto">
            <a href="{{ route('restaurant.orders.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2 flex-fill flex-md-grow-0 px-3 py-2 rounded-3 fw-semibold" style="min-height: 44px;">
                <i class="bi bi-receipt"></i>
                <span>Ver Comandas</span>
            </a>
            @can('create', App\Models\RestaurantTable::class)
                <a href="{{ route('restaurant.tables.create') }}" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 flex-fill flex-md-grow-0 px-3 py-2 rounded-3 fw-semibold shadow-sm" style="min-height: 44px;">
                    <i class="bi bi-plus-circle"></i>
                    <span>Nueva Mesa</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- Indicadores de estado del salón (KPIs Adaptables) --}}
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-circle bg-secondary bg-opacity-10 p-2 p-md-3 me-2 me-md-3 text-secondary flex-shrink-0">
                        <i class="bi bi-grid fs-5 fs-md-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold d-block text-truncate">Total Mesas</span>
                        <h4 class="fw-bold mb-0">{{ $stats['total'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-circle bg-success bg-opacity-10 p-2 p-md-3 me-2 me-md-3 text-success flex-shrink-0">
                        <i class="bi bi-check-circle-fill fs-5 fs-md-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold d-block text-truncate">Libres</span>
                        <h4 class="fw-bold mb-0 text-success">{{ $stats['available'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 p-md-3 me-2 me-md-3 text-primary flex-shrink-0">
                        <i class="bi bi-people-fill fs-5 fs-md-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold d-block text-truncate">Ocupadas</span>
                        <h4 class="fw-bold mb-0 text-primary">{{ $stats['occupied'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 p-md-3 me-2 me-md-3 text-warning flex-shrink-0">
                        <i class="bi bi-hourglass-split fs-5 fs-md-4"></i>
                    </div>
                    <div>
                        <span class="text-muted small fw-semibold d-block text-truncate">En Cobro</span>
                        <h4 class="fw-bold mb-0 text-warning">{{ $stats['billed'] }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Grid de Mesas --}}
    @if($tables->isEmpty())
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body">
                <i class="bi bi-grid-3x3 text-muted display-4 mb-3"></i>
                <h5 class="fw-bold">No hay mesas configuradas aún</h5>
                <p class="text-muted">Crea las mesas de tu salón, barra o terraza para comenzar a tomar comandas.</p>
                @can('create', App\Models\RestaurantTable::class)
                    <a href="{{ route('restaurant.tables.create') }}" class="btn btn-primary mt-2">
                        <i class="bi bi-plus-circle me-1"></i> Crear Primera Mesa
                    </a>
                @endcan
            </div>
        </div>
    @else
        <div class="row g-2 g-md-3">
            @foreach($tables as $table)
                @php
                    $isAvailable = $table->status === 'available';
                    $isOccupied = $table->status === 'occupied';
                    $isBilled = $table->status === 'billed';
                    $activeOrder = $table->activeOrder;
                @endphp
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="card h-100 shadow-sm rounded-3 border-2 transition-all {{ $isAvailable ? 'border-success' : ($isOccupied ? 'border-primary' : 'border-warning') }}">
                        <div class="card-header bg-white border-0 p-2 d-flex justify-content-between align-items-center">
                            <span class="badge px-2 py-1 fw-bold {{ $isAvailable ? 'bg-success text-white' : ($isOccupied ? 'bg-primary text-white' : 'bg-warning text-dark') }}" style="font-size: 0.68rem;">
                                {{ $isAvailable ? 'LIBRE' : ($isOccupied ? 'OCUPADA' : 'EN COBRO') }}
                            </span>

                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-light text-secondary border small px-1.5 py-0.5" title="Capacidad: {{ $table->capacity }} personas" style="font-size: 0.68rem;">
                                    <i class="bi bi-person"></i>{{ $table->capacity }}
                                </span>

                                @can('update', $table)
                                    <div class="dropdown">
                                        <button class="btn btn-xs btn-light rounded-circle text-muted p-0 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" type="button" data-bs-toggle="dropdown" aria-label="Opciones de mesa">
                                            <i class="bi bi-three-dots-vertical" style="font-size: 0.75rem;"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                            <li>
                                                <a class="dropdown-item py-1.5 small" href="{{ route('restaurant.tables.edit', $table) }}">
                                                    <i class="bi bi-pencil me-1.5 text-primary"></i> Editar Mesa
                                                </a>
                                            </li>
                                            @if(!$isOccupied && !$isBilled)
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li>
                                                    <button type="button" class="dropdown-item text-danger py-1.5 small btn-delete-table"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#deleteTableModal"
                                                            data-action-url="{{ route('restaurant.tables.destroy', $table) }}"
                                                            data-table-name="{{ $table->name }}">
                                                        <i class="bi bi-trash me-1.5"></i> Eliminar
                                                    </button>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                @endcan
                            </div>
                        </div>

                        <div class="card-body text-center p-2 d-flex flex-column justify-content-between">
                            <div class="mb-1">
                                <i class="bi bi-aspect-ratio fs-4 {{ $isAvailable ? 'text-success' : ($isOccupied ? 'text-primary' : 'text-warning') }}"></i>
                                <h6 class="fw-bold text-dark mb-0 text-truncate px-1" title="{{ $table->name }}">{{ $table->name }}</h6>
                            </div>

                            @if(($isOccupied || $isBilled) && $activeOrder)
                                <div class="bg-light rounded-2 p-1.5 mb-1 text-start small border" style="font-size: 0.75rem;">
                                    <div class="d-flex justify-content-between align-items-center mb-0.5">
                                        <span class="fw-bold text-dark text-truncate" style="max-width: 80px;" title="{{ $activeOrder->order_number }}">#{{ $activeOrder->order_number }}</span>
                                        <span class="fw-bold text-primary">${{ number_format($activeOrder->total, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.7rem;">
                                        <span class="text-truncate" style="max-width: 80px;" title="{{ !empty($activeOrder->customer_name) ? $activeOrder->customer_name : ($activeOrder->user->name ?? 'Usuario') }}">
                                            <i class="bi bi-person-badge"></i> {{ !empty($activeOrder->customer_name) ? $activeOrder->customer_name : ($activeOrder->user->name ?? 'Usuario') }}
                                        </span>
                                        <span class="text-secondary fw-semibold">
                                            <i class="bi bi-clock"></i> {{ $activeOrder->created_at->diffForHumans(null, true) }}
                                        </span>
                                    </div>
                                </div>
                            @else
                                <div class="py-1 text-muted small" style="font-size: 0.72rem;">
                                    <span class="text-success"><i class="bi bi-check2"></i> Disponible</span>
                                </div>
                            @endif
                        </div>

                        <div class="card-footer bg-white border-0 p-2 pt-0">
                            @if($isAvailable || !$activeOrder)
                                <a href="{{ route('restaurant.orders.create', ['table_id' => $table->id]) }}" class="btn btn-sm btn-outline-success w-100 fw-bold d-flex align-items-center justify-content-center gap-1 py-2" style="min-height: 44px; font-size: 0.85rem;">
                                    <i class="bi bi-plus-lg"></i>
                                    <span>Abrir Mesa</span>
                                </a>
                            @else
                                <a href="{{ route('restaurant.orders.show', $activeOrder) }}" class="btn btn-sm btn-primary w-100 fw-bold d-flex align-items-center justify-content-center py-2" style="min-height: 44px; font-size: 0.85rem;">
                                    <i class="bi bi-receipt me-1"></i> Ver Comanda
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Modal Corporativo de Eliminación de Mesa --}}
<div class="modal fade" id="deleteTableModal" tabindex="-1" aria-labelledby="deleteTableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <h5 class="modal-title fw-bold d-flex align-items-center" id="deleteTableModalLabel">
                    <i class="bi bi-trash3-fill me-2 fs-5"></i> Eliminar Mesa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill display-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">¿Estás seguro de eliminar esta mesa?</h5>
                <div class="alert alert-light border rounded-3 p-3 text-start small mb-3">
                    <div>
                        <span class="text-muted">Mesa:</span>
                        <strong class="text-dark" id="deleteTableName">-</strong>
                    </div>
                </div>
                <p class="text-muted small mb-0">
                    Esta acción eliminará la mesa de la configuración del salón y <strong>no se puede deshacer</strong>.
                </p>
            </div>
            <div class="modal-footer bg-light px-4 py-3 justify-content-end gap-2 border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form id="deleteTableForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
                        <i class="bi bi-trash3 me-1"></i> Sí, Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteModal = document.getElementById('deleteTableModal');
    if (!deleteModal) return;

    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const actionUrl = button.getAttribute('data-action-url');
        const tableName = button.getAttribute('data-table-name');

        const form = document.getElementById('deleteTableForm');
        const nameEl = document.getElementById('deleteTableName');

        if (form) form.action = actionUrl;
        if (nameEl) nameEl.textContent = tableName || '-';
    });
});
</script>
@endsection
