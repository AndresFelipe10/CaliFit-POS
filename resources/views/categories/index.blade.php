@extends('layouts.app')

@section('title', 'Categorías de Productos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Categorías de Productos</h3>
        <p class="text-muted small mb-0">Organiza los productos de <strong>{{ $currentBusiness->name }}</strong> en categorías.</p>
    </div>
    @can('create', App\Models\Category::class)
        <a href="{{ route('categories.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-plus-circle-fill me-1"></i> Nueva Categoría
        </a>
    @endcan
</div>

<div class="card card-custom p-3 bg-white mb-4">
    <form method="GET" action="{{ route('categories.index') }}" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="Buscar por nombre o descripción..." 
                       value="{{ $search }}">
            </div>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary rounded-pill px-3">Buscar</button>
            @if ($search)
                <a href="{{ route('categories.index') }}" class="btn btn-light rounded-pill px-3 text-secondary">Limpiar</a>
            @endif
        </div>
    </form>
</div>

<div class="card card-custom p-0 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted">
                    <th class="ps-4">Nombre</th>
                    <th>Descripción</th>
                    <th>Productos Asignados</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td class="ps-4 fw-semibold">
                            <div class="d-flex align-items-center gap-2">
                                <span class="p-2 bg-primary-subtle text-primary rounded-3">
                                    <i class="bi bi-tag-fill"></i>
                                </span>
                                <span>{{ $category->name }}</span>
                            </div>
                        </td>
                        <td class="text-secondary small">
                            {{ $category->description ?? '—' }}
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border px-2.5 py-1.5 rounded-pill">
                                <i class="bi bi-box-seam me-1 text-primary"></i> {{ $category->products_count }} productos
                            </span>
                        </td>
                        <td>
                            @if ($category->is_active)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> Activa
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-dash-circle-fill me-1"></i> Inactiva
                                </span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-inline-flex gap-2">
                                @can('update', $category)
                                    <a href="{{ route('categories.edit', $category) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Editar">
                                        <i class="bi bi-pencil-fill me-1"></i> Editar
                                    </a>
                                @endcan

                                @can('delete', $category)
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm rounded-pill px-3 btn-delete-category" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteCategoryModal" 
                                            data-action-url="{{ route('categories.destroy', $category) }}" 
                                            data-category-name="{{ $category->name }}"
                                            title="Eliminar">
                                        <i class="bi bi-trash-fill me-1"></i> Eliminar
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-tags display-4 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron categorías registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($categories->hasPages())
        <div class="p-3 border-top">
            {{ $categories->links() }}
        </div>
    @endif
</div>

{{-- Modal Corporativo de Eliminación de Categoría --}}
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <h5 class="modal-title fw-bold d-flex align-items-center" id="deleteCategoryModalLabel">
                    <i class="bi bi-trash3-fill me-2 fs-5"></i> Eliminar Categoría
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill display-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">¿Estás seguro de eliminar esta categoría?</h5>
                <div class="alert alert-light border rounded-3 p-3 text-start small mb-3">
                    <span class="text-muted">Categoría:</span>
                    <strong class="text-dark d-block text-truncate" id="deleteCategoryName">-</strong>
                </div>
                <p class="text-muted small mb-0">
                    Esta acción eliminará la categoría del sistema y <strong>no se puede deshacer</strong>.
                </p>
            </div>
            <div class="modal-footer bg-light px-4 py-3 justify-content-end gap-2 border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form id="deleteCategoryForm" method="POST" action="">
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
    const deleteModal = document.getElementById('deleteCategoryModal');
    if (!deleteModal) return;

    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const actionUrl = button.getAttribute('data-action-url');
        const categoryName = button.getAttribute('data-category-name');

        const form = document.getElementById('deleteCategoryForm');
        const nameEl = document.getElementById('deleteCategoryName');

        if (form) form.action = actionUrl;
        if (nameEl) nameEl.textContent = categoryName || '-';
    });
});
</script>
@endsection
