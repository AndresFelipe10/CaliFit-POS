@extends('layouts.app')

@section('title', 'Crear Fórmula de Receta | SaborCaliFit')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <a href="{{ route('recipes.index') }}" class="text-decoration-none text-muted small fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Volver a Recetas
                </a>
                <h3 class="fw-bold mb-0 text-dark mt-1">
                    <i class="bi bi-journal-plus me-2" style="color: #10B981;"></i>Nueva Fórmula de Receta por Porción
                </h3>
            </div>
        </div>

        {{-- Alerta de Regla de Negocio: Exclusión Mutua Frijoles vs Lentejas --}}
        <div id="exclusionAlert" class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 d-none" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-octagon-fill fs-3 me-3 text-danger"></i>
                <div>
                    <h6 class="fw-bold mb-1">Conflicto de Exclusión Mutua Detectado</h6>
                    <div class="small">
                        No se permite configurar simultáneamente <strong>Frijoles</strong> y <strong>Lentejas</strong> en la misma receta base. Debes elegir solo una leguminosa principal según el estándar nutricional de SaborCaliFit.
                    </div>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Errores de validación:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('recipes.store') }}" method="POST" id="recipeCreateForm">
            @csrf

            {{-- 1. Selección de Modo: Plato Existente vs Plato Nuevo --}}
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4">
                <h5 class="fw-bold text-dark mb-3">
                    <i class="bi bi-egg-fried me-2" style="color: #10B981;"></i>1. Destino de la Receta
                </h5>

                <div class="d-flex gap-4 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="dish_mode" id="modeExisting" value="existing" {{ old('dish_mode', 'existing') === 'existing' ? 'checked' : '' }} onchange="toggleDishMode()">
                        <label class="form-check-label fw-semibold" for="modeExisting">
                            Asignar a un Plato Existente en Carta
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="dish_mode" id="modeNew" value="new" {{ old('dish_mode') === 'new' ? 'checked' : '' }} onchange="toggleDishMode()">
                        <label class="form-check-label fw-semibold" for="modeNew">
                            Registrar Nuevo Plato y su Receta
                        </label>
                    </div>
                </div>

                {{-- Contenedor Plato Existente --}}
                <div id="existingDishContainer" class="{{ old('dish_mode', 'existing') === 'existing' ? '' : 'd-none' }}">
                    <label for="product_id" class="form-label small fw-bold text-muted">Seleccionar Plato de la Carta: <span class="text-danger">*</span></label>
                    <select name="product_id" id="product_id" class="form-select select2">
                        <option value="">-- Seleccione el plato al que configurará la receta --</option>
                        @foreach($dishes as $dish)
                            <option value="{{ $dish->id }}" {{ old('product_id') == $dish->id ? 'selected' : '' }} data-price="{{ $dish->precio_venta }}">
                                {{ $dish->nombre }} ({{ $dish->sku }}) — ${{ number_format($dish->precio_venta, 0, ',', '.') }}
                                @if($dish->receta && $dish->receta->items->count())
                                    [Ya tiene fórmula]
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Contenedor Plato Nuevo --}}
                <div id="newDishContainer" class="{{ old('dish_mode') === 'new' ? '' : 'd-none' }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label small fw-bold text-muted">Nombre del Plato: <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre') }}" placeholder="Ej: Bowl Proteico CaliFit">
                        </div>
                        <div class="col-md-3">
                            <label for="sku" class="form-label small fw-bold text-muted">Código SKU (Opcional):</label>
                            <input type="text" name="sku" id="sku" class="form-control font-monospace" value="{{ old('sku') }}" placeholder="Ej: PLATO-BOWL01">
                        </div>
                        <div class="col-md-3">
                            <label for="precio_venta" class="form-label small fw-bold text-muted">Precio Venta ($): <span class="text-danger">*</span></label>
                            <input type="number" step="100" min="0" name="precio_venta" id="precio_venta" class="form-control" value="{{ old('precio_venta') }}" placeholder="Ej: 24000">
                        </div>
                        <div class="col-md-6">
                            <label for="category_id" class="form-label small fw-bold text-muted">Categoría: <span class="text-danger">*</span></label>
                            <select name="category_id" id="category_id" class="form-select">
                                <option value="">-- Seleccionar categoría --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="descripcion" class="form-label small fw-bold text-muted">Descripción del Plato:</label>
                            <input type="text" name="descripcion" id="descripcion" class="form-control" value="{{ old('descripcion') }}" placeholder="Breve descripción o ingredientes clave">
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Configuración de Insumos y Porciones --}}
            <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-basket3 me-2" style="color: #10B981;"></i>2. Insumos y Cantidad por Porción
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold" id="addRowBtn">
                        <i class="bi bi-plus-circle me-1"></i> Agregar Insumo
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-borderless align-middle" id="ingredientsTable">
                        <thead class="table-light rounded-3">
                            <tr class="small text-uppercase text-muted">
                                <th style="width: 50%;">Insumo / Materia Prima</th>
                                <th style="width: 20%;">Unidad Base</th>
                                <th style="width: 20%;">Gramos / Mililitros</th>
                                <th style="width: 10%;" class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="ingredientsBody">
                            @php
                                $oldItems = old('items', [
                                    ['ingrediente_id' => '', 'cantidad_por_porcion' => '']
                                ]);
                            @endphp

                            @foreach ($oldItems as $index => $item)
                                <tr class="ingredient-row">
                                    <td>
                                        <select name="items[{{ $index }}][ingrediente_id]" class="form-select ingredient-select" required>
                                            <option value="">Seleccionar insumo...</option>
                                            @foreach ($insumos as $insumo)
                                                <option value="{{ $insumo->id }}"
                                                        data-unit="{{ $insumo->unidad_base }}"
                                                        data-name="{{ $insumo->nombre }}"
                                                        {{ ($item['ingrediente_id'] ?? '') == $insumo->id ? 'selected' : '' }}>
                                                    {{ $insumo->nombre }} (Stock: {{ number_format($insumo->stock, 2) }} {{ $insumo->unidad_base }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border unit-badge px-2 py-1 font-monospace">--</span>
                                    </td>
                                    <td>
                                        <div class="input-group">
                                            <input type="number"
                                                   step="0.01"
                                                   min="0.01"
                                                   name="items[{{ $index }}][cantidad_por_porcion]"
                                                   class="form-control quantity-input"
                                                   value="{{ $item['cantidad_por_porcion'] ?? '' }}"
                                                   placeholder="Ej. 150"
                                                   required>
                                            <span class="input-group-text bg-light text-muted unit-addon">--</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm rounded-circle delete-row-btn" title="Eliminar fila">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                    <a href="{{ route('recipes.index') }}" class="btn btn-light rounded-pill px-4 fw-semibold text-muted">
                        Cancelar
                    </a>
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" id="saveRecipeBtn" style="background-color: #10B981; border-color: #10B981;">
                        <i class="bi bi-check2-circle me-1"></i> Guardar Fórmula de Receta
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<template id="ingredientRowTemplate">
    <tr class="ingredient-row">
        <td>
            <select name="items[__INDEX__][ingrediente_id]" class="form-select ingredient-select" required>
                <option value="">Seleccionar insumo...</option>
                @foreach ($insumos as $insumo)
                    <option value="{{ $insumo->id }}"
                            data-unit="{{ $insumo->unidad_base }}"
                            data-name="{{ $insumo->nombre }}">
                        {{ $insumo->nombre }} (Stock: {{ number_format($insumo->stock, 2) }} {{ $insumo->unidad_base }})
                    </option>
                @endforeach
            </select>
        </td>
        <td>
            <span class="badge bg-light text-dark border unit-badge px-2 py-1 font-monospace">--</span>
        </td>
        <td>
            <div class="input-group">
                <input type="number"
                       step="0.01"
                       min="0.01"
                       name="items[__INDEX__][cantidad_por_porcion]"
                       class="form-control quantity-input"
                       placeholder="Ej. 150"
                       required>
                <span class="input-group-text bg-light text-muted unit-addon">--</span>
            </div>
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-outline-danger btn-sm rounded-circle delete-row-btn" title="Eliminar fila">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
function toggleDishMode() {
    const isExisting = document.getElementById('modeExisting').checked;
    const existingContainer = document.getElementById('existingDishContainer');
    const newContainer = document.getElementById('newDishContainer');
    const productSelect = document.getElementById('product_id');
    const nameInput = document.getElementById('nombre');
    const priceInput = document.getElementById('precio_venta');
    const catSelect = document.getElementById('category_id');

    if (isExisting) {
        existingContainer.classList.remove('d-none');
        newContainer.classList.add('d-none');
        productSelect.required = true;
        nameInput.required = false;
        priceInput.required = false;
        catSelect.required = false;
    } else {
        existingContainer.classList.add('d-none');
        newContainer.classList.remove('d-none');
        productSelect.required = false;
        nameInput.required = true;
        priceInput.required = true;
        catSelect.required = true;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('ingredientsBody');
    const addRowBtn = document.getElementById('addRowBtn');
    const rowTemplate = document.getElementById('ingredientRowTemplate').innerHTML;
    const saveRecipeBtn = document.getElementById('saveRecipeBtn');
    const exclusionAlert = document.getElementById('exclusionAlert');
    const form = document.getElementById('recipeCreateForm');

    toggleDishMode();

    function updateUnits(row) {
        const select = row.querySelector('.ingredient-select');
        const unitBadge = row.querySelector('.unit-badge');
        const unitAddon = row.querySelector('.unit-addon');

        const selectedOption = select.options[select.selectedIndex];
        const unit = selectedOption ? selectedOption.getAttribute('data-unit') : '--';

        if (unitBadge) unitBadge.textContent = unit || '--';
        if (unitAddon) unitAddon.textContent = unit || '--';
    }

    function checkMutualExclusion() {
        const selects = tableBody.querySelectorAll('.ingredient-select');
        let hasFrijoles = false;
        let hasLentejas = false;

        selects.forEach(sel => {
            const opt = sel.options[sel.selectedIndex];
            if (opt && opt.value) {
                const name = (opt.getAttribute('data-name') || '').toLowerCase();
                if (name.includes('frijol')) hasFrijoles = true;
                if (name.includes('lenteja')) hasLentejas = true;
            }
        });

        if (hasFrijoles && hasLentejas) {
            exclusionAlert.classList.remove('d-none');
            saveRecipeBtn.disabled = true;
        } else {
            exclusionAlert.classList.add('d-none');
            saveRecipeBtn.disabled = false;
        }
    }

    function initRowEvents(row) {
        const select = row.querySelector('.ingredient-select');
        const deleteBtn = row.querySelector('.delete-row-btn');

        select.addEventListener('change', () => {
            updateUnits(row);
            checkMutualExclusion();
        });

        deleteBtn.addEventListener('click', () => {
            if (tableBody.querySelectorAll('.ingredient-row').length > 1) {
                row.remove();
                checkMutualExclusion();
            } else {
                alert('La receta debe contener al menos un ingrediente por porción.');
            }
        });

        updateUnits(row);
    }

    tableBody.querySelectorAll('.ingredient-row').forEach(initRowEvents);
    checkMutualExclusion();

    addRowBtn.addEventListener('click', () => {
        const index = tableBody.querySelectorAll('.ingredient-row').length + Date.now();
        const newRowHtml = rowTemplate.replace(/__INDEX__/g, index);
        const tempDiv = document.createElement('tbody');
        tempDiv.innerHTML = newRowHtml;
        const newRow = tempDiv.firstElementChild;

        tableBody.appendChild(newRow);
        initRowEvents(newRow);
    });

    form.addEventListener('submit', (e) => {
        checkMutualExclusion();
        if (saveRecipeBtn.disabled) {
            e.preventDefault();
            alert('Por favor resuelva el conflicto de exclusión mutua (frijoles vs lentejas) antes de guardar.');
        }
    });
});
</script>
@endpush
@endsection
