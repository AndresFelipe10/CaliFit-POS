@extends('layouts.app')

@section('title', 'Directorio de Proveedores')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1" style="color: #111827;">
            <i class="bi bi-truck me-2" style="color: #10B981;"></i>Directorio de Proveedores
        </h3>
        <p class="text-muted small mb-0">Gestión de distribuidores de insumos, materias primas y suplementación deportiva.</p>
    </div>
    <button type="button" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background-color: #10B981;" data-bs-toggle="modal" data-bs-target="#createSupplierModal">
        <i class="bi bi-plus-circle-fill me-1"></i> Registrar Proveedor
    </button>
</div>

<!-- Filtro de Búsqueda -->
<div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
    <form method="GET" action="{{ route('suppliers.index') }}" class="row g-2 align-items-center">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por razón social, NIT, contacto o teléfono..." 
                       value="{{ request('search') }}">
            </div>
        </div>

        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">Todos los estados</option>
                <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Activos</option>
                <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactivos</option>
            </select>
        </div>

        <div class="col-md-3 text-end d-flex gap-2">
            <button type="submit" class="btn text-white rounded-pill flex-grow-1 fw-semibold" style="background-color: #10B981;">Filtrar</button>
            @if (request()->hasAny(['search', 'status']))
                <a href="{{ route('suppliers.index') }}" class="btn btn-light rounded-pill text-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Tabla de Proveedores -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted" style="letter-spacing: 0.5px;">
                    <th class="ps-4">Razón Social / Proveedor</th>
                    <th>NIT / Identificación</th>
                    <th>Persona de Contacto</th>
                    <th>Contacto</th>
                    <th>Facturas / Gastos</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark">{{ $supplier->name }}</div>
                            @if ($supplier->address)
                                <small class="text-muted d-block text-truncate" style="max-width: 200px;">
                                    <i class="bi bi-geo-alt me-1"></i>{{ $supplier->address }}
                                </small>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border font-monospace px-2 py-1">{{ $supplier->nit }}</span>
                        </td>
                        <td>
                            <span class="text-dark small">{{ $supplier->contact_name ?: '—' }}</span>
                        </td>
                        <td>
                            <div class="small">
                                @if ($supplier->phone)
                                    <div><i class="bi bi-telephone text-muted me-1"></i>{{ $supplier->phone }}</div>
                                @endif
                                @if ($supplier->email)
                                    <div class="text-muted"><i class="bi bi-envelope text-muted me-1"></i>{{ $supplier->email }}</div>
                                @endif
                                @if (!$supplier->phone && !$supplier->email)
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @php
                                $expensesCount = (int) ($supplier->expenses_count ?? 0);
                            @endphp
                            <span class="badge rounded-pill px-2.5 py-1 fw-semibold d-inline-flex align-items-center" 
                                  style="background-color: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; font-size: 0.8rem;">
                                <i class="bi bi-receipt-cutoff me-1.5" style="margin-right: 6px;"></i>
                                <span>{{ $expensesCount }} {{ $expensesCount === 1 ? 'factura' : 'facturas' }}</span>
                            </span>
                        </td>
                        <td>
                            <form action="{{ route('suppliers.toggle-status', $supplier) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm p-0 border-0" title="Click para alternar estado">
                                    @if ($supplier->is_active)
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                            <i class="bi bi-check-circle-fill me-1"></i> Activo
                                        </span>
                                    @else
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                                            <i class="bi bi-x-circle-fill me-1"></i> Inactivo
                                        </span>
                                    @endif
                                </button>
                            </form>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 me-1" title="Ver compras y facturas">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" 
                                        class="btn btn-sm btn-light border rounded-pill px-3 edit-supplier-btn"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editSupplierModal"
                                        data-id="{{ $supplier->id }}"
                                        data-name="{{ $supplier->name }}"
                                        data-nit="{{ $supplier->nit }}"
                                        data-contact-name="{{ $supplier->contact_name }}"
                                        data-phone="{{ $supplier->phone }}"
                                        data-email="{{ $supplier->email }}"
                                        data-address="{{ $supplier->address }}"
                                        data-update-url="{{ route('suppliers.update', $supplier) }}"
                                        title="Editar datos">
                                    <i class="bi bi-pencil-square text-secondary"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-truck fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron proveedores registrados con los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($suppliers->hasPages())
        <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-muted">Mostrando {{ $suppliers->firstItem() }} - {{ $suppliers->lastItem() }} de {{ $suppliers->total() }} proveedores</span>
            {{ $suppliers->links() }}
        </div>
    @endif
</div>

{{-- Modal Registrar Proveedor --}}
<div class="modal fade" id="createSupplierModal" tabindex="-1" aria-labelledby="createSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="createSupplierModalLabel">
                    <i class="bi bi-truck me-2" style="color: #10B981;"></i>Registrar Proveedor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('suppliers.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small">Razón Social / Nombre Comercial <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Ej: Suplementos Colombia S.A.S" required maxlength="150">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small">NIT / Identificación Tributaria <span class="text-danger">*</span></label>
                        <input type="text" name="nit" class="form-control font-monospace" placeholder="Ej: 900123456-7" required maxlength="30">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small">Nombre de Contacto / Asesor Comercial</label>
                        <input type="text" name="contact_name" class="form-control" placeholder="Ej: Carlos Gómez" maxlength="100">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark small">Teléfono / Celular</label>
                            <input type="text" name="phone" class="form-control" placeholder="Ej: 3201234567" maxlength="20">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark small">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control" placeholder="ventas@proveedor.com" maxlength="150">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold text-dark small">Dirección / Sede</label>
                        <input type="text" name="address" class="form-control" placeholder="Ej: Cra 100 # 16-20, Cali" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background-color: #10B981;">
                        <i class="bi bi-check2-circle me-1"></i> Registrar Proveedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Editar Proveedor --}}
<div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="editSupplierModalLabel">
                    <i class="bi bi-pencil-square me-2" style="color: #10B981;"></i>Editar Proveedor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editSupplierForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small">Razón Social <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required maxlength="150">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small">NIT / Identificación <span class="text-danger">*</span></label>
                        <input type="text" name="nit" id="edit_nit" class="form-control font-monospace" required maxlength="30">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small">Nombre de Contacto</label>
                        <input type="text" name="contact_name" id="edit_contact_name" class="form-control" maxlength="100">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark small">Teléfono</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control" maxlength="20">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark small">Correo Electrónico</label>
                            <input type="email" name="email" id="edit_email" class="form-control" maxlength="150">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold text-dark small">Dirección</label>
                        <input type="text" name="address" id="edit_address" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background-color: #10B981;">
                        <i class="bi bi-check2-circle me-1"></i> Actualizar Proveedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editButtons = document.querySelectorAll('.edit-supplier-btn');
    const editForm = document.getElementById('editSupplierForm');
    const editName = document.getElementById('edit_name');
    const editNit = document.getElementById('edit_nit');
    const editContact = document.getElementById('edit_contact_name');
    const editPhone = document.getElementById('edit_phone');
    const editEmail = document.getElementById('edit_email');
    const editAddress = document.getElementById('edit_address');

    editButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            editForm.action = this.dataset.updateUrl;
            editName.value = this.dataset.name || '';
            editNit.value = this.dataset.nit || '';
            editContact.value = this.dataset.contactName || '';
            editPhone.value = this.dataset.phone || '';
            editEmail.value = this.dataset.email || '';
            editAddress.value = this.dataset.address || '';
        });
    });
});
</script>
@endpush
@endsection
