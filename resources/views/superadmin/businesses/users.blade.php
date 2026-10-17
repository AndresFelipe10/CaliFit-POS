@extends('layouts.superadmin')

@section('title', 'Usuarios de ' . $business->name)

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
    <div>
        <h1 class="fs-3 fw-bold text-white mb-1">Usuarios de {{ $business->name }}</h1>
        <p class="text-secondary small mb-0">Listado de colaboradores vinculados a este negocio</p>
    </div>
    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 w-100 w-sm-auto">
        <a href="{{ route('superadmin.businesses.users.create', $business) }}" class="btn btn-primary fw-semibold d-inline-flex align-items-center justify-content-center" style="min-height: 42px;">
            <i class="bi bi-person-plus me-1"></i> Agregar Colaborador
        </a>
        <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 d-inline-flex align-items-center justify-content-center" style="min-height: 42px;">
            <i class="bi bi-arrow-left me-1"></i> Volver a Negocios
        </a>
    </div>
</div>

@if (session('temp_password'))
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4 text-white" style="background-color: #198754;">
        <i class="bi bi-key-fill fs-2 me-3"></i>
        <div class="flex-grow-1">
            <h5 class="fw-bold mb-1">¡Colaborador Registrado con Éxito!</h5>
            <p class="mb-0">
                Credenciales para entregar al comercio: 
                <strong>{{ session('created_user_email') }}</strong> &bull; 
                Contraseña temporal: <code class="fs-6 fw-bold bg-dark text-warning px-2 py-1 rounded">{{ session('temp_password') }}</code>
                <span class="badge bg-warning text-dark ms-2">Debe cambiarla al primer inicio de sesión</span>
            </p>
        </div>
    </div>
@endif

<div class="card card-dark border-secondary border-opacity-25 rounded-4 overflow-hidden shadow-sm">
    <div class="table-responsive">
        <table class="table table-dark-custom align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width: 160px;">Nombre</th>
                    <th style="min-width: 180px;">Correo Electrónico</th>
                    <th style="min-width: 130px;">Rol</th>
                    <th style="min-width: 110px;">Estado</th>
                    <th style="min-width: 150px;">Fecha de Vinculación</th>
                    <th class="text-end" style="min-width: 140px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    @php
                        $role = $roles[$user->pivot->role_id] ?? null;
                        $isActive = (bool) $user->pivot->is_active;
                    @endphp
                    <tr>
                        <td class="fw-medium">
                            <i class="bi bi-person-circle text-primary me-2"></i>{{ $user->name }}
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if ($role && $role->slug === 'admin')
                                <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25">
                                    <i class="bi bi-shield-check me-1"></i>Administrador
                                </span>
                            @else
                                <span class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-25">
                                    <i class="bi bi-person me-1"></i>{{ $role->name ?? 'Colaborador' }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @if ($isActive)
                                <span class="badge bg-success bg-opacity-25 text-success">
                                    <i class="bi bi-check-circle me-1"></i>Activo
                                </span>
                            @else
                                <span class="badge bg-danger bg-opacity-25 text-danger">
                                    <i class="bi bi-x-circle me-1"></i>Inactivo
                                </span>
                            @endif
                        </td>
                        <td class="small text-secondary">
                            {{ $user->pivot->created_at ? \Carbon\Carbon::parse($user->pivot->created_at)->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td class="text-end">
                            @if ($isActive)
                                <button type="button" 
                                        class="btn btn-outline-danger btn-sm btn-toggle-superadmin-user" 
                                        title="Desactivar usuario"
                                        data-bs-toggle="modal"
                                        data-bs-target="#toggleSuperadminUserModal"
                                        data-action-url="{{ route('superadmin.businesses.toggleUserStatus', [$business, $user]) }}"
                                        data-user-name="{{ $user->name }}"
                                        data-target-action="deactivate">
                                    <i class="bi bi-person-x me-1"></i>Desactivar
                                </button>
                            @else
                                <button type="button" 
                                        class="btn btn-outline-success btn-sm btn-toggle-superadmin-user" 
                                        title="Activar usuario"
                                        data-bs-toggle="modal"
                                        data-bs-target="#toggleSuperadminUserModal"
                                        data-action-url="{{ route('superadmin.businesses.toggleUserStatus', [$business, $user]) }}"
                                        data-user-name="{{ $user->name }}"
                                        data-target-action="activate">
                                    <i class="bi bi-person-check me-1"></i>Activar
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-secondary">
                            No hay usuarios vinculados a este negocio.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal Superadmin de Cambio de Estado de Colaborador --}}
<div class="modal fade" id="toggleSuperadminUserModal" tabindex="-1" aria-labelledby="toggleSuperadminUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-dark border-secondary border-opacity-50 shadow rounded-4 overflow-hidden text-light">
            <div class="modal-header py-3" id="toggleSaModalHeader">
                <h5 class="modal-title fw-bold d-flex align-items-center text-white" id="toggleSuperadminUserModalLabel">
                    <i class="bi me-2 fs-5" id="toggleSaModalIcon"></i>
                    <span id="toggleSaModalTitle">Cambiar Estado</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert d-flex align-items-start mb-0 rounded-3" id="toggleSaModalAlert" role="alert">
                    <i class="bi fs-3 me-3 flex-shrink-0" id="toggleSaAlertIcon"></i>
                    <div>
                        <div class="fw-semibold text-white mb-1" id="toggleSaModalMessage"></div>
                        <div class="small text-secondary" id="toggleSaModalSubtext"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-dark border-top border-secondary border-opacity-25 px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form action="" method="POST" id="toggleSuperadminUserForm" class="d-inline m-0" data-no-disable>
                    @csrf
                    <button type="submit" class="btn rounded-pill px-4 fw-bold" id="btnConfirmToggleSaUser">
                        Confirmar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('toggleSuperadminUserModal');
    if (!modalEl) return;

    const modalHeader = document.getElementById('toggleSaModalHeader');
    const modalTitle = document.getElementById('toggleSaModalTitle');
    const modalIcon = document.getElementById('toggleSaModalIcon');
    const modalAlert = document.getElementById('toggleSaModalAlert');
    const alertIcon = document.getElementById('toggleSaAlertIcon');
    const modalMessage = document.getElementById('toggleSaModalMessage');
    const modalSubtext = document.getElementById('toggleSaModalSubtext');
    const statusForm = document.getElementById('toggleSuperadminUserForm');
    const btnConfirm = document.getElementById('btnConfirmToggleSaUser');

    modalEl.addEventListener('show.bs.modal', function (event) {
        const triggerBtn = event.relatedTarget;
        if (!triggerBtn) return;

        const actionUrl = triggerBtn.getAttribute('data-action-url') || '';
        const userName = triggerBtn.getAttribute('data-user-name') || '';
        const targetAction = triggerBtn.getAttribute('data-target-action') || 'deactivate';

        if (statusForm) statusForm.action = actionUrl;

        if (targetAction === 'deactivate') {
            modalHeader.className = 'modal-header py-3 bg-danger text-white';
            modalTitle.textContent = 'Desactivar Usuario';
            modalIcon.className = 'bi bi-person-x-fill me-2 fs-5';

            modalAlert.className = 'alert alert-warning bg-warning bg-opacity-10 border border-warning border-opacity-25 d-flex align-items-start mb-0 rounded-3';
            alertIcon.className = 'bi bi-exclamation-triangle-fill text-warning fs-3 me-3 flex-shrink-0';
            modalMessage.innerHTML = `¿Seguro que deseas desactivar a <strong>${escapeHtml(userName)}</strong> en este negocio?`;
            modalSubtext.textContent = 'El usuario perderá el acceso a las funciones del emprendimiento.';

            btnConfirm.className = 'btn btn-danger rounded-pill px-4 fw-bold';
            btnConfirm.innerHTML = '<i class="bi bi-person-x me-1"></i> Desactivar';
        } else {
            modalHeader.className = 'modal-header py-3 bg-success text-white';
            modalTitle.textContent = 'Activar Usuario';
            modalIcon.className = 'bi bi-person-check-fill me-2 fs-5';

            modalAlert.className = 'alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 d-flex align-items-start mb-0 rounded-3';
            alertIcon.className = 'bi bi-check-circle-fill text-success fs-3 me-3 flex-shrink-0';
            modalMessage.innerHTML = `¿Seguro que deseas activar a <strong>${escapeHtml(userName)}</strong> en este negocio?`;
            modalSubtext.textContent = 'El usuario podrá volver a ingresar y operar.';

            btnConfirm.className = 'btn btn-success rounded-pill px-4 fw-bold';
            btnConfirm.innerHTML = '<i class="bi bi-person-check me-1"></i> Activar';
        }

        btnConfirm.disabled = false;
        btnConfirm.removeAttribute('data-original-html');
    });

    if (statusForm) {
        statusForm.addEventListener('submit', function () {
            if (!btnConfirm) return;
            btnConfirm.setAttribute('data-original-html', btnConfirm.innerHTML);
            btnConfirm.disabled = true;
            btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Procesando...';
        });
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (!btnConfirm) return;
        btnConfirm.disabled = false;
        const orig = btnConfirm.getAttribute('data-original-html');
        if (orig) {
            btnConfirm.innerHTML = orig;
            btnConfirm.removeAttribute('data-original-html');
        }
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endpush
