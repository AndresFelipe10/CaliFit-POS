@extends('layouts.app')

@section('title', 'Equipo y Colaboradores | SaborCaliFit')

@section('content')
<div class="container-fluid px-2 px-md-3 py-3">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark">
                <i class="bi bi-people-fill text-success me-2"></i>Equipo y Colaboradores
            </h1>
            <p class="text-muted small mb-0">Gestión de accesos, credenciales, roles operativos y contraseñas temporales (SaborCaliFit Bochalema).</p>
        </div>
        <div>
            <button type="button" class="btn btn-califit btn-sm px-3 py-2 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="bi bi-person-plus-fill me-1"></i> Nuevo Colaborador
            </button>
        </div>
    </div>

    {{-- Banner de Contraseña Temporal Generada --}}
    @if(session('temp_password'))
        <div class="alert alert-warning border border-warning shadow-sm p-3 mb-3 rounded-3">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-key-fill fs-4 text-warning-emphasis"></i>
                <h6 class="fw-bold mb-0 text-dark">¡Contraseña Temporal Generada para {{ session('temp_user_name') }}!</h6>
            </div>
            <p class="small text-muted mb-2">Entregue esta clave temporal al colaborador. El sistema le exigirá cambiarla de inmediato en su primer inicio de sesión:</p>
            <div class="d-inline-flex align-items-center gap-2 bg-white px-3 py-2 rounded border border-warning">
                <span class="text-muted small">Correo: <strong>{{ session('temp_user_email') }}</strong></span>
                <span class="text-secondary">|</span>
                <span class="text-muted small">Clave temporal:</span>
                <code class="fs-6 fw-bold text-danger">{{ session('temp_password') }}</code>
            </div>
        </div>
    @endif

    {{-- Tabla de Colaboradores --}}
    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th class="ps-3" style="width: 60px;">ID</th>
                        <th>Colaborador</th>
                        <th>Correo Electrónico</th>
                        <th style="width: 160px;">Rol Operativo</th>
                        <th class="text-center" style="width: 120px;">Estado</th>
                        <th class="text-center" style="width: 150px;">Cambio de Clave</th>
                        <th class="text-end pe-3" style="width: 180px;">Acciones</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse($users as $userItem)
                        @php
                            $roleSlug = $userItem->role?->slug;
                            $roleBadge = match($roleSlug) {
                                'admin' => 'bg-success text-white',
                                'employee' => 'bg-secondary text-white',
                                default => 'bg-dark text-white',
                            };
                            $roleName = $userItem->role?->name ?? 'Sin Rol';
                        @endphp
                        <tr>
                            <td class="ps-3 font-monospace text-muted">{{ $userItem->id }}</td>
                            <td>
                                <div class="fw-bold text-dark">{{ $userItem->name }}</div>
                                @if($userItem->id === auth()->id())
                                    <span class="badge bg-light text-success border" style="font-size: 0.7rem;">Tu cuenta actual</span>
                                @endif
                            </td>
                            <td class="font-monospace text-dark">{{ $userItem->email }}</td>
                            <td>
                                <span class="badge {{ $roleBadge }} px-2 py-1 rounded-pill fw-semibold">
                                    {{ $roleName }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($userItem->is_active)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Activo</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Inactivo</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($userItem->must_change_password)
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                        <i class="bi bi-clock-history me-1"></i>Pendiente
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border px-2 py-1">Actualizada</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1">
                                    {{-- Editar Datos / Rol --}}
                                    <button type="button" class="btn btn-sm btn-outline-primary p-1" title="Editar Colaborador"
                                            onclick="openEditModal({{ $userItem->id }}, '{{ addslashes($userItem->name) }}', '{{ $userItem->email }}', {{ $userItem->role_id ?? 'null' }})">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    {{-- Restablecer Contraseña --}}
                                    <form method="POST" action="{{ route('users.reset-password', $userItem) }}" class="d-inline"
                                          data-confirm="¿Restablecer la contraseña de {{ $userItem->name }}? Se generará una clave temporal de acceso."
                                          data-confirm-title="¿Restablecer Contraseña?"
                                          data-confirm-btn="Sí, Restablecer Clave"
                                          data-confirm-btn-class="btn-warning text-dark"
                                          data-confirm-icon="bi-key">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-warning p-1" title="Restablecer Contraseña">
                                            <i class="bi bi-key"></i>
                                        </button>
                                    </form>

                                    {{-- Activar / Desactivar --}}
                                    @if($userItem->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.toggle', $userItem) }}" class="d-inline"
                                              @if($userItem->is_active)
                                                  data-confirm="¿Desactivar el acceso al sistema para el colaborador '{{ $userItem->name }}'?"
                                                  data-confirm-title="¿Desactivar Acceso?"
                                                  data-confirm-btn="Sí, Desactivar"
                                                  data-confirm-btn-class="btn-danger"
                                                  data-confirm-icon="bi-person-x"
                                              @else
                                                  data-confirm="¿Restablecer y activar el acceso al sistema para el colaborador '{{ $userItem->name }}'?"
                                                  data-confirm-title="¿Activar Acceso?"
                                                  data-confirm-btn="Sí, Activar"
                                                  data-confirm-btn-class="btn-success"
                                                  data-confirm-icon="bi-person-check"
                                              @endif>
                                            @csrf
                                            @method('PATCH')
                                            @if($userItem->is_active)
                                                <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Desactivar acceso">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            @else
                                                <button type="submit" class="btn btn-sm btn-outline-success p-1" title="Activar acceso">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            @endif
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                No se encontraron colaboradores registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Crear Colaborador --}}
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('users.store') }}" class="modal-content">
            @csrf
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2 text-success"></i>Nuevo Colaborador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Ej: Santiago Pérez" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="ejemplo@califit.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Rol Operativo en CaliFit <span class="text-danger">*</span></label>
                    <select name="role_id" class="form-select" required>
                        <option value="">-- Seleccionar Rol --</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="alert alert-light border small text-muted mb-0">
                    <i class="bi bi-shield-check me-1 text-success"></i> Se generará una contraseña temporal segura y se forzará al colaborador a cambiarla en su primer acceso.
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-califit btn-sm fw-bold px-4">Crear Colaborador</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Editar Colaborador --}}
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="editUserForm" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Colaborador</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="editUserName" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="editUserEmail" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Rol Operativo en CaliFit <span class="text-danger">*</span></label>
                    <select name="role_id" id="editUserRoleId" class="form-select" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm fw-bold px-4">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, email, roleId) {
    const form = document.getElementById('editUserForm');
    form.action = `/users/${id}`;
    document.getElementById('editUserName').value = name;
    document.getElementById('editUserEmail').value = email;
    document.getElementById('editUserRoleId').value = roleId;
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}
</script>
@endsection
