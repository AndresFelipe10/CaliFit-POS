@extends('layouts.app')

@section('title', 'Directorio de Proveedores')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Directorio de Proveedores</h3>
        <p class="text-muted small mb-0">Gestión de proveedores y contactos de suministro para <strong>{{ $currentBusiness->name }}</strong>.</p>
    </div>
    @can('create', App\Models\Supplier::class)
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-truck me-1"></i> Registrar Proveedor
        </a>
    @endcan
</div>

<!-- Filtro de Búsqueda -->
<div class="card card-custom p-3 bg-white mb-4">
    <form method="GET" action="{{ route('suppliers.index') }}" class="row g-2 align-items-center">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por proveedor, contacto, NIT o teléfono..." 
                       value="{{ $search }}">
            </div>
        </div>

        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">Todos los estados</option>
                <option value="1" {{ $selectedStatus === '1' ? 'selected' : '' }}>Activos</option>
                <option value="0" {{ $selectedStatus === '0' ? 'selected' : '' }}>Inactivos</option>
            </select>
        </div>

        <div class="col-md-3 text-end d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary rounded-pill flex-grow-1">Filtrar</button>
            @if ($search || $selectedStatus !== null)
                <a href="{{ route('suppliers.index') }}" class="btn btn-light rounded-pill text-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Tabla de Proveedores -->
<div class="card card-custom p-0 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted">
                    <th class="ps-4">Proveedor / Empresa</th>
                    <th>NIT / Identificación</th>
                    <th>Persona de Contacto</th>
                    <th>Contacto</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-semibold text-dark">{{ $supplier->name }}</div>
                            @if ($supplier->address)
                                <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $supplier->address }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($supplier->identification_number)
                                <span class="badge bg-light text-dark border font-monospace">{{ $supplier->identification_number }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($supplier->contact_name)
                                <div><i class="bi bi-person me-1 text-primary"></i>{{ $supplier->contact_name }}</div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="small">
                                @if ($supplier->phone)
                                    <div><i class="bi bi-telephone me-1 text-secondary"></i>{{ $supplier->phone }}</div>
                                @endif
                                @if ($supplier->email)
                                    <div class="text-secondary"><i class="bi bi-envelope me-1"></i>{{ $supplier->email }}</div>
                                @endif
                                @if (! $supplier->phone && ! $supplier->email)
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($supplier->is_active)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                    Activo
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                                    Inactivo
                                </span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-light btn-sm rounded-pill px-2.5 text-secondary" title="Ver Detalle">
                                    <i class="bi bi-eye"></i>
                                </a>

                                @can('update', $supplier)
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan

                                @can('delete', $supplier)
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm rounded-pill px-2.5 btn-delete-supplier" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteSupplierModal" 
                                            data-action-url="{{ route('suppliers.destroy', $supplier) }}" 
                                            data-supplier-name="{{ $supplier->name }}"
                                            data-supplier-nit="{{ $supplier->nit ?? 'N/A' }}"
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-truck display-4 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron proveedores registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($suppliers->hasPages())
        <div class="p-3 border-top">
            {{ $suppliers->links() }}
        </div>
    @endif
</div>

{{-- Modal Corporativo de Eliminación de Proveedor --}}
<div class="modal fade" id="deleteSupplierModal" tabindex="-1" aria-labelledby="deleteSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <h5 class="modal-title fw-bold d-flex align-items-center" id="deleteSupplierModalLabel">
                    <i class="bi bi-trash3-fill me-2 fs-5"></i> Eliminar Proveedor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill display-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">¿Estás seguro de eliminar este proveedor?</h5>
                <div class="alert alert-light border rounded-3 p-3 text-start small mb-3">
                    <div class="mb-1">
                        <span class="text-muted">Razón Social / Nombre:</span>
                        <strong class="text-dark d-block text-truncate" id="deleteSupplierName">-</strong>
                    </div>
                    <div>
                        <span class="text-muted">NIT / Documento:</span>
                        <strong class="text-dark" id="deleteSupplierNit">-</strong>
                    </div>
                </div>
                <p class="text-muted small mb-0">
                    Esta acción eliminará el proveedor del sistema y <strong>no se puede deshacer</strong>.
                </p>
            </div>
            <div class="modal-footer bg-light px-4 py-3 justify-content-end gap-2 border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form id="deleteSupplierForm" method="POST" action="">
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
    const deleteModal = document.getElementById('deleteSupplierModal');
    if (!deleteModal) return;

    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const actionUrl = button.getAttribute('data-action-url');
        const supplierName = button.getAttribute('data-supplier-name');
        const supplierNit = button.getAttribute('data-supplier-nit');

        const form = document.getElementById('deleteSupplierForm');
        const nameEl = document.getElementById('deleteSupplierName');
        const nitEl = document.getElementById('deleteSupplierNit');

        if (form) form.action = actionUrl;
        if (nameEl) nameEl.textContent = supplierName || '-';
        if (nitEl) nitEl.textContent = supplierNit || 'N/A';
    });
});
</script>
@endsection
