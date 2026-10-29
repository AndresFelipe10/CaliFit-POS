@extends('layouts.app')

@section('title', $isEdit ? 'Editar Adición: ' . $addition->nombre : 'Nueva Adición Reutilizable')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <a href="{{ route('additions.index') }}" class="text-decoration-none text-muted small fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Volver a Adiciones
                </a>
                <h3 class="fw-bold mb-0 text-dark mt-1">
                    {{ $isEdit ? 'Editar Adición' : 'Registrar Nueva Adición Reutilizable' }}
                </h3>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Por favor corrige los siguientes errores:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <form action="{{ $isEdit ? route('additions.update', $addition) : route('additions.store') }}" method="POST" id="additionForm">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                <div class="row g-3 mb-4">
                    {{-- Nombre de la Adición --}}
                    <div class="col-md-8">
                        <label class="form-label fw-semibold text-dark small">Nombre de la Adición <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="nombre" 
                               id="nombre" 
                               class="form-control @error('nombre') is-invalid @enderror" 
                               placeholder="Ej: Aguacate adicional 80g o Porción extra de Pollo 100g" 
                               value="{{ old('nombre', $addition->nombre ?? '') }}" 
                               required 
                               maxlength="100">
                        <div class="form-text small">Nombre descriptivo con el gramaje o presentación sugerida.</div>
                    </div>

                    {{-- Precio de Venta --}}
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-dark small">Precio Adicional (COP) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">$</span>
                            <input type="text" 
                                   inputmode="numeric"
                                   name="precio" 
                                   id="precio" 
                                   class="form-control fw-bold text-success currency-input @error('precio') is-invalid @enderror" 
                                   placeholder="0" 
                                   value="{{ old('precio', ($addition->precio ?? 0) ? number_format((float)($addition->precio ?? 0), 0, ',', '.') : '0') }}" 
                                   required>
                        </div>
                        <div class="form-text small">Monto que se suma al cobrar el plato.</div>
                    </div>
                </div>

                {{-- Sección: Insumos Consumidos por Porción --}}
                <div class="border rounded-4 p-3 bg-light bg-opacity-50 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-basket2 me-2" style="color: #10B981;"></i>Insumos Consumidos por la Adición <span class="text-danger">*</span>
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold" id="addIngRowBtn">
                            <i class="bi bi-plus-circle me-1"></i> Agregar Insumo
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0" id="additionIngTable">
                            <thead>
                                <tr class="small text-uppercase text-muted">
                                    <th style="width: 55%;">Insumo / Materia Prima</th>
                                    <th style="width: 20%;">Unidad</th>
                                    <th style="width: 20%;">Cantidad Consumida</th>
                                    <th style="width: 5%;" class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="additionIngBody">
                                @php
                                    $existingIngs = old('ingredients', $addition->items->map(function($i) {
                                        return [
                                            'ingrediente_id' => $i->ingrediente_id,
                                            'cantidad' => (float)$i->cantidad,
                                        ];
                                    })->toArray());
                                @endphp

                                @forelse ($existingIngs as $index => $ing)
                                    <tr class="addition-ing-row">
                                        <td>
                                            <select name="ingredients[{{ $index }}][ingrediente_id]" class="form-select ing-select" required>
                                                <option value="">Seleccionar insumo...</option>
                                                @foreach ($insumos as $ins)
                                                    <option value="{{ $ins->id }}" data-unit="{{ $ins->unidad_base }}" {{ ($ing['ingrediente_id'] ?? '') == $ins->id ? 'selected' : '' }}>
                                                        {{ $ins->nombre }} (Stock: {{ $ins->formatted_stock }} {{ strtolower($ins->unidad_base) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border font-monospace unit-badge py-2 px-3">
                                                GRAMO
                                            </span>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="ingredients[{{ $index }}][cantidad]" 
                                                   class="form-control quantity-input font-monospace" 
                                                   step="0.001" 
                                                   min="0.001" 
                                                   placeholder="0.000" 
                                                   value="{{ $ing['cantidad'] ?? '' }}" 
                                                   required>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm rounded-circle remove-ing-btn" title="Quitar insumo">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="addition-ing-row">
                                        <td>
                                            <select name="ingredients[0][ingrediente_id]" class="form-select ing-select" required>
                                                <option value="">Seleccionar insumo...</option>
                                                @foreach ($insumos as $ins)
                                                    <option value="{{ $ins->id }}" data-unit="{{ $ins->unidad_base }}">
                                                        {{ $ins->nombre }} (Stock: {{ $ins->formatted_stock }} {{ strtolower($ins->unidad_base) }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-secondary border font-monospace unit-badge py-2 px-3">
                                                GRAMO
                                            </span>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="ingredients[0][cantidad]" 
                                                   class="form-control quantity-input font-monospace" 
                                                   step="0.001" 
                                                   min="0.001" 
                                                   placeholder="0.000" 
                                                   required>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-outline-danger btn-sm rounded-circle remove-ing-btn" title="Quitar insumo">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Sección: Platos Autorizados en Carta --}}
                <div class="border rounded-4 p-3 bg-white mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-egg-fried me-2" style="color: #10B981;"></i>Platos en los que estará disponible este Extra
                            </h6>
                            <span class="text-muted small">Selecciona los platos de cocina donde los cajeros podrán añadir este extra.</span>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-light border text-secondary" id="selectAllDishes">Todos</button>
                            <button type="button" class="btn btn-light border text-secondary" id="deselectAllDishes">Ninguno</button>
                        </div>
                    </div>

                    @php
                        $selectedDishIds = old('dish_ids', $addition->products->pluck('id')->toArray());
                    @endphp

                    <div class="row g-2">
                        @foreach ($dishes as $dish)
                            <div class="col-md-6 col-lg-4">
                                <div class="form-check p-2 border rounded-3 bg-light bg-opacity-25 h-100">
                                    <input class="form-check-input ms-1 dish-checkbox" 
                                           type="checkbox" 
                                           name="dish_ids[]" 
                                           value="{{ $dish->id }}" 
                                           id="dish_{{ $dish->id }}"
                                           {{ in_array($dish->id, $selectedDishIds) ? 'checked' : '' }}>
                                    <label class="form-check-label ms-2 small fw-semibold text-dark text-truncate d-block" for="dish_{{ $dish->id }}" title="{{ $dish->nombre }}">
                                        {{ $dish->nombre }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('additions.index') }}" class="btn btn-light rounded-pill px-4 text-secondary">
                        Cancelar
                    </a>
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background-color: #10B981;">
                        <i class="bi bi-check2-circle me-1"></i> {{ $isEdit ? 'Actualizar Adición' : 'Guardar Adición' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Template para fila de insumo --}}
<template id="additionIngTemplate">
    <tr class="addition-ing-row">
        <td>
            <select name="ingredients[INDEX][ingrediente_id]" class="form-select ing-select" required>
                <option value="">Seleccionar insumo...</option>
                @foreach ($insumos as $ins)
                    <option value="{{ $ins->id }}" data-unit="{{ $ins->unidad_base }}">
                        {{ $ins->nombre }} (Stock: {{ $ins->formatted_stock }} {{ strtolower($ins->unidad_base) }})
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <span class="badge bg-light text-secondary border font-monospace unit-badge py-2 px-3">
                GRAMO
            </span>
        </td>
        <td>
            <input type="number" 
                   name="ingredients[INDEX][cantidad]" 
                   class="form-control quantity-input font-monospace" 
                   step="0.001" 
                   min="0.001" 
                   placeholder="0.000" 
                   required>
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-outline-danger btn-sm rounded-circle remove-ing-btn" title="Quitar insumo">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('additionIngBody');
    const addRowBtn = document.getElementById('addIngRowBtn');
    const template = document.getElementById('additionIngTemplate');
    const selectAllBtn = document.getElementById('selectAllDishes');
    const deselectAllBtn = document.getElementById('deselectAllDishes');
    const dishCheckboxes = document.querySelectorAll('.dish-checkbox');

    function updateRowUnits(row) {
        const select = row.querySelector('.ing-select');
        const badge = row.querySelector('.unit-badge');
        const selectedOpt = select.options[select.selectedIndex];
        if (selectedOpt && selectedOpt.dataset.unit) {
            badge.textContent = selectedOpt.dataset.unit;
        } else {
            badge.textContent = 'GRAMO';
        }
    }

    function reindexRows() {
        const rows = tableBody.querySelectorAll('.addition-ing-row');
        rows.forEach((row, index) => {
            const select = row.querySelector('.ing-select');
            const qty = row.querySelector('.quantity-input');
            select.name = `ingredients[${index}][ingrediente_id]`;
            qty.name = `ingredients[${index}][cantidad]`;
            updateRowUnits(row);
        });
    }

    addRowBtn.addEventListener('click', function () {
        const clone = template.content.cloneNode(true);
        const newRow = clone.querySelector('.addition-ing-row');
        tableBody.appendChild(newRow);
        bindRowEvents(newRow);
        reindexRows();
    });

    function bindRowEvents(row) {
        const select = row.querySelector('.ing-select');
        const removeBtn = row.querySelector('.remove-ing-btn');

        select.addEventListener('change', function () {
            updateRowUnits(row);
        });

        removeBtn.addEventListener('click', function () {
            if (tableBody.querySelectorAll('.addition-ing-row').length > 1) {
                row.remove();
                reindexRows();
            } else {
                califitNotify('La adición debe consumir al menos un insumo.', 'warning', 'Insumo Requerido');
            }
        });
    }

    tableBody.querySelectorAll('.addition-ing-row').forEach(row => {
        bindRowEvents(row);
        updateRowUnits(row);
    });

    selectAllBtn.addEventListener('click', function () {
        dishCheckboxes.forEach(cb => cb.checked = true);
    });

    deselectAllBtn.addEventListener('click', function () {
        dishCheckboxes.forEach(cb => cb.checked = false);
    });
});
</script>
@endpush
@endsection
