@extends('layouts.app')

@section('title', 'Directorio de Clientes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1" style="color: #111827;">
            <i class="bi bi-people-fill me-2" style="color: #10B981;"></i>Directorio de Clientes
        </h3>
        <p class="text-muted small mb-0">Base de datos de atletas, clientes frecuentes y consumidores de SaborCaliFit.</p>
    </div>
    <button type="button" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background-color: #10B981;" data-bs-toggle="modal" data-bs-target="#createCustomerModal">
        <i class="bi bi-person-plus-fill me-1"></i> Registrar Cliente
    </button>
</div>

<!-- Filtro de Búsqueda -->
<div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
    <form method="GET" action="{{ route('customers.index') }}" class="row g-2 align-items-center">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por nombre, documento/cédula, teléfono o correo..." 
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
                <a href="{{ route('customers.index') }}" class="btn btn-light rounded-pill text-secondary" title="Limpiar"><i class="bi bi-x-lg"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Tabla de Clientes -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted" style="letter-spacing: 0.5px;">
                    <th class="ps-4">Cliente</th>
                    <th>Documento / NIT</th>
                    <th>Contacto</th>
                    <th>Dirección</th>
                    <th>Compras</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark">{{ $customer->name }}</div>
                            <small class="text-muted">Registrado: {{ $customer->created_at ? $customer->created_at->format('d/m/Y') : '—' }}</small>
                        </td>
                        <td>
                            @if ($customer->document)
                                <span class="badge bg-light text-secondary border font-monospace px-2 py-1">{{ $customer->document }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="small">
                                @if ($customer->phone)
                                    <div><i class="bi bi-telephone text-muted me-1"></i>{{ $customer->phone }}</div>
                                @endif
                                @if ($customer->email)
                                    <div class="text-muted"><i class="bi bi-envelope text-muted me-1"></i>{{ $customer->email }}</div>
                                @endif
                                @if (!$customer->phone && !$customer->email)
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="small text-muted text-truncate" style="max-width: 200px;">
                                {{ $customer->address ?: '—' }}
                            </div>
                        </td>
                        <td>
                            @php
                                $purchasesCount = (int) ($customer->sales_count ?? 0);
                            @endphp
                            <span class="badge rounded-pill px-2.5 py-1 fw-semibold d-inline-flex align-items-center" 
                                  style="background-color: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; font-size: 0.8rem;">
                                <i class="bi bi-receipt me-1.5" style="margin-right: 6px;"></i>
                                <span>{{ $purchasesCount }} {{ $purchasesCount === 1 ? 'compra' : 'compras' }}</span>
                            </span>
                        </td>
                        <td>
                            <form action="{{ route('customers.toggle-status', $customer) }}" method="POST" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm p-0 border-0" title="Click para alternar estado">
                                    @if ($customer->is_active)
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
                                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 me-1" title="Ver compras e historial">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" 
                                        class="btn btn-sm btn-light border rounded-pill px-3 edit-customer-btn"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editCustomerModal"
                                        data-id="{{ $customer->id }}"
                                        data-nombre="{{ $customer->name }}"
                                        data-document="{{ $customer->document }}"
                                        data-phone="{{ $customer->phone }}"
                                        data-email="{{ $customer->email }}"
                                        data-address="{{ $customer->address }}"
                                        data-update-url="{{ route('customers.update', $customer) }}"
                                        title="Editar datos">
                                    <i class="bi bi-pencil-square text-secondary"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron clientes registrados con los criterios seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($customers->hasPages())
        <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-muted">Mostrando {{ $customers->firstItem() }} - {{ $customers->lastItem() }} de {{ $customers->total() }} clientes</span>
            {{ $customers->links() }}
        </div>
    @endif
</div>

{{-- Modal Registrar Cliente --}}
<div class="modal fade" id="createCustomerModal" tabindex="-1" aria-labelledby="createCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="createCustomerModalLabel">
                    <i class="bi bi-person-plus-fill me-2" style="color: #10B981;"></i>Registrar Nuevo Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('customers.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    {{-- Alerta Informativa DIAN --}}
                    <div class="alert alert-info border-0 rounded-3 small py-2 mb-3">
                        <i class="bi bi-info-circle-fill me-1"></i> Por resolución de la DIAN, el documento <code>222222222222</code> está reservado exclusivamente para Consumidor Final y no se puede registrar individualmente.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror @error('name') is-invalid @enderror" placeholder="Ej: Andrés Felipe Pérez" value="{{ old('nombre', old('name')) }}" required maxlength="150">
                        @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small">Cédula / Documento / NIT <span class="text-danger">*</span></label>
                        <input type="text" name="document" class="form-control font-monospace @error('document') is-invalid @enderror @error('document_number') is-invalid @enderror" placeholder="Ej: 1144123456" value="{{ old('document', old('document_number')) }}" required maxlength="30">
                        @error('document') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @error('document_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark small">Teléfono / WhatsApp</label>
                            <input type="text" name="telefono" class="form-control @error('telefono') is-invalid @enderror @error('phone') is-invalid @enderror" placeholder="Ej: 3151234567" value="{{ old('telefono', old('phone')) }}" maxlength="20">
                            @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark small">Correo Electrónico</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="cliente@correo.com" value="{{ old('email') }}" maxlength="150">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold text-dark small">Dirección de Despacho / Residencia</label>
                        <input type="text" name="direccion" class="form-control @error('direccion') is-invalid @enderror @error('address') is-invalid @enderror" placeholder="Ej: Calle 45 # 122-30, Bochalema" value="{{ old('direccion', old('address')) }}" maxlength="255">
                        @error('direccion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background-color: #10B981;">
                        <i class="bi bi-check2-circle me-1"></i> Registrar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Editar Cliente --}}
<div class="modal fade" id="editCustomerModal" tabindex="-1" aria-labelledby="editCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="editCustomerModalLabel">
                    <i class="bi bi-pencil-square me-2" style="color: #10B981;"></i>Editar Cliente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCustomerForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="edit_customer_url" id="edit_customer_url" value="{{ old('edit_customer_url') }}">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control @error('nombre') is-invalid @enderror @error('name') is-invalid @enderror" required maxlength="150">
                        @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small">Cédula / Documento / NIT <span class="text-danger">*</span></label>
                        <input type="text" name="document" id="edit_document" class="form-control font-monospace @error('document') is-invalid @enderror @error('document_number') is-invalid @enderror" required maxlength="30">
                        @error('document') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @error('document_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark small">Teléfono / WhatsApp</label>
                            <input type="text" name="telefono" id="edit_telefono" class="form-control @error('telefono') is-invalid @enderror @error('phone') is-invalid @enderror" maxlength="20">
                            @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold text-dark small">Correo Electrónico</label>
                            <input type="email" name="email" id="edit_email" class="form-control @error('email') is-invalid @enderror" maxlength="150">
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold text-dark small">Dirección</label>
                        <input type="text" name="direccion" id="edit_direccion" class="form-control @error('direccion') is-invalid @enderror @error('address') is-invalid @enderror" maxlength="255">
                        @error('direccion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4 text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background-color: #10B981;">
                        <i class="bi bi-check2-circle me-1"></i> Actualizar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editButtons = document.querySelectorAll('.edit-customer-btn');
    const editForm = document.getElementById('editCustomerForm');
    const editNombre = document.getElementById('edit_nombre');
    const editDoc = document.getElementById('edit_document');
    const editTel = document.getElementById('edit_telefono');
    const editEmail = document.getElementById('edit_email');
    const editDir = document.getElementById('edit_direccion');
    const editUrlInput = document.getElementById('edit_customer_url');

    editButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            editForm.action = this.dataset.updateUrl;
            if (editUrlInput) editUrlInput.value = this.dataset.updateUrl;
            editNombre.value = this.dataset.nombre || '';
            editDoc.value = this.dataset.document || '';
            editTel.value = this.dataset.phone || '';
            editEmail.value = this.dataset.email || '';
            editDir.value = this.dataset.address || '';
        });
    });

    @if ($errors->any())
        @if (old('_method') === 'PUT')
            const editModalEl = document.getElementById('editCustomerModal');
            if (editModalEl) {
                const oldUrl = editUrlInput ? editUrlInput.value : '';
                if (oldUrl) editForm.action = oldUrl;
                editNombre.value = @json(old('nombre', old('name', '')));
                editDoc.value = @json(old('document', old('document_number', '')));
                editTel.value = @json(old('telefono', old('phone', '')));
                editEmail.value = @json(old('email', ''));
                editDir.value = @json(old('direccion', old('address', '')));
                new bootstrap.Modal(editModalEl).show();
            }
        @else
            const createModalEl = document.getElementById('createCustomerModal');
            if (createModalEl) {
                new bootstrap.Modal(createModalEl).show();
            }
        @endif
    @endif
});
</script>
@endpush
@endsection
