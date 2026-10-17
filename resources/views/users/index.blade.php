@extends('layouts.app')

@section('title', 'Equipo de Trabajo')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h1 class="fs-3 fw-bold text-dark mb-1">Equipo de Trabajo</h1>
        <p class="text-muted small mb-0">Visualiza los colaboradores asignados a <strong>{{ $currentBusiness->name ?? auth()->user()->business->name }}</strong> y administra sus roles o estado de acceso.</p>
    </div>
</div>

<div class="card card-custom p-0 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted">
                    <th class="ps-4">Usuario</th>
                    <th>Email</th>
                    <th>Rol en el Negocio</th>
                    <th>Estado</th>
                    <th>Fecha de Vinculación</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($members as $member)
                    @php
                        $role = $roles->get($member->pivot->role_id);
                        $isActive = (bool) $member->pivot->is_active;
                        $isSelf = auth()->id() === $member->id;
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-light text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                    <i class="bi bi-person-fill fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $member->name }}</div>
                                    @if ($isSelf)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle small">Tú</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="text-secondary">{{ $member->email }}</span>
                        </td>
                        <td>
                            @if ($role && $role->slug === \App\Models\Role::ROLE_ADMIN)
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 rounded-pill">
                                    <i class="bi bi-shield-lock-fill me-1"></i> {{ $role->name }}
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1.5 rounded-pill">
                                    <i class="bi bi-person-badge-fill me-1"></i> {{ $role?->name ?? 'Empleado' }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @if ($isActive)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> Activo
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-dash-circle-fill me-1"></i> Inactivo
                                </span>
                            @endif
                        </td>
                        <td class="text-muted small">
                            {{ $member->pivot->created_at ? \Carbon\Carbon::parse($member->pivot->created_at)->format('d/m/Y') : '—' }}
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-inline-flex gap-2">
                                @can('update', $member)
                                    <a href="{{ route('users.edit', $member) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Editar Rol">
                                        <i class="bi bi-pencil-fill me-1"></i> Editar
                                    </a>
                                @endcan

                                @if (! $isSelf)
                                    @can('deactivate', $member)
                                        @if ($isActive)
                                            <button type="button"
                                                    class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-semibold btn-toggle-collaborator-status"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#toggleCollaboratorStatusModal"
                                                    data-action-url="{{ route('users.toggleActive', $member) }}"
                                                    data-http-method="POST"
                                                    data-user-name="{{ $member->name }}"
                                                    data-user-email="{{ $member->email }}"
                                                    data-target-action="deactivate">
                                                <i class="bi bi-person-x me-1"></i> Desactivar
                                            </button>
                                        @else
                                            <button type="button"
                                                    class="btn btn-outline-success btn-sm rounded-pill px-3 fw-semibold btn-toggle-collaborator-status"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#toggleCollaboratorStatusModal"
                                                    data-action-url="{{ route('users.toggleActive', $member) }}"
                                                    data-http-method="POST"
                                                    data-user-name="{{ $member->name }}"
                                                    data-user-email="{{ $member->email }}"
                                                    data-target-action="activate">
                                                <i class="bi bi-person-check me-1"></i> Activar
                                            </button>
                                        @endif
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Modal Corporativo de Confirmación de Cambio de Estado --}}
<div class="modal fade" id="toggleCollaboratorStatusModal" tabindex="-1" aria-labelledby="toggleCollaboratorStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header text-white py-3" id="toggleStatusModalHeader">
                <h5 class="modal-title fw-bold d-flex align-items-center" id="toggleCollaboratorStatusModalLabel">
                    <i class="bi me-2 fs-5" id="toggleStatusModalIcon"></i>
                    <span id="toggleStatusModalTitle">Cambiar Estado de Colaborador</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert d-flex align-items-start mb-0 rounded-3" id="toggleStatusModalAlert" role="alert">
                    <i class="bi fs-3 me-3 flex-shrink-0" id="toggleStatusAlertIcon"></i>
                    <div>
                        <div class="fw-semibold text-dark mb-1" id="toggleStatusModalMessage"></div>
                        <div class="small text-muted" id="toggleStatusModalSubtext"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form action="" method="POST" id="toggleCollaboratorStatusForm" class="d-inline m-0" data-no-disable>
                    @csrf
                    <input type="hidden" name="_method" id="toggleCollaboratorHttpMethod" value="POST">
                    <button type="submit" class="btn rounded-pill px-4 fw-bold" id="btnConfirmToggleStatus">
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
    const modalEl = document.getElementById('toggleCollaboratorStatusModal');
    if (!modalEl) return;

    const modalHeader = document.getElementById('toggleStatusModalHeader');
    const modalTitle = document.getElementById('toggleStatusModalTitle');
    const modalIcon = document.getElementById('toggleStatusModalIcon');
    const modalAlert = document.getElementById('toggleStatusModalAlert');
    const alertIcon = document.getElementById('toggleStatusAlertIcon');
    const modalMessage = document.getElementById('toggleStatusModalMessage');
    const modalSubtext = document.getElementById('toggleStatusModalSubtext');
    const statusForm = document.getElementById('toggleCollaboratorStatusForm');
    const httpMethodInput = document.getElementById('toggleCollaboratorHttpMethod');
    const btnConfirm = document.getElementById('btnConfirmToggleStatus');

    modalEl.addEventListener('show.bs.modal', function (event) {
        const triggerBtn = event.relatedTarget;
        if (!triggerBtn) return;

        const actionUrl = triggerBtn.getAttribute('data-action-url') || '';
        const httpMethod = triggerBtn.getAttribute('data-http-method') || 'POST';
        const userName = triggerBtn.getAttribute('data-user-name') || '';
        const userEmail = triggerBtn.getAttribute('data-user-email') || '';
        const targetAction = triggerBtn.getAttribute('data-target-action') || 'deactivate';

        if (statusForm) statusForm.action = actionUrl;
        if (httpMethodInput) httpMethodInput.value = httpMethod;

        if (targetAction === 'deactivate') {
            modalHeader.className = 'modal-header text-white py-3 bg-danger';
            modalTitle.textContent = 'Desactivar Colaborador';
            modalIcon.className = 'bi bi-person-x-fill me-2 fs-5';

            modalAlert.className = 'alert alert-warning d-flex align-items-start mb-0 rounded-3';
            alertIcon.className = 'bi bi-exclamation-triangle-fill text-warning fs-3 me-3 flex-shrink-0';
            modalMessage.innerHTML = `¿Estás seguro de desactivar el acceso de <strong>${escapeHtml(userName)}</strong> (${escapeHtml(userEmail)})?`;
            modalSubtext.textContent = 'El colaborador no podrá iniciar sesión ni operar en el sistema hasta que su cuenta sea reactivada.';

            btnConfirm.className = 'btn btn-danger rounded-pill px-4 fw-bold';
            btnConfirm.innerHTML = '<i class="bi bi-person-x me-1"></i> Sí, Desactivar Acceso';
        } else {
            modalHeader.className = 'modal-header text-white py-3 bg-success';
            modalTitle.textContent = 'Reactivar Colaborador';
            modalIcon.className = 'bi bi-person-check-fill me-2 fs-5';

            modalAlert.className = 'alert alert-success d-flex align-items-start mb-0 rounded-3';
            alertIcon.className = 'bi bi-check-circle-fill text-success fs-3 me-3 flex-shrink-0';
            modalMessage.innerHTML = `¿Confirmas que deseas reactivar el acceso de <strong>${escapeHtml(userName)}</strong> (${escapeHtml(userEmail)})?`;
            modalSubtext.textContent = 'El colaborador podrá volver a iniciar sesión inmediatamente con su rol asignado.';

            btnConfirm.className = 'btn btn-success rounded-pill px-4 fw-bold';
            btnConfirm.innerHTML = '<i class="bi bi-person-check me-1"></i> Sí, Reactivar Acceso';
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
