@extends('layouts.app')

@section('title', 'Editar Fórmula de Receta: ' . $product->nombre)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <a href="{{ route('recipes.index') }}" class="text-decoration-none text-muted small fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Volver a Recetas
                </a>
                <h3 class="fw-bold mb-0 text-dark mt-1">
                    Fórmula de Receta por Porción
                </h3>
            </div>
            <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold">
                <i class="bi bi-pencil me-1"></i> Ficha del Plato
            </a>
        </div>

        {{-- Tarjeta Informativa del Plato --}}
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 mb-4 border-start border-4 border-emerald" style="border-left-color: #10B981 !important;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="badge bg-light text-secondary border font-monospace px-2 py-1">{{ $product->sku }}</span>
                    <h4 class="fw-bold text-dark mb-1 mt-1">{{ $product->nombre }}</h4>
                    <p class="text-muted small mb-0">{{ $product->descripcion ?: 'Plato del menú saludable de SaborCaliFit Bochalema.' }}</p>
                </div>
                <div class="text-end">
                    <span class="text-muted small d-block">Precio Venta al Público</span>
                    <h3 class="fw-bold mb-0 text-success">${{ number_format($product->precio_venta, 0, ',', '.') }}</h3>
                </div>
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

        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <form action="{{ route('recipes.update', $product) }}" method="POST" id="recipeForm">
                @csrf
                @method('PUT')

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-basket3 me-2" style="color: #10B981;"></i>Insumos y Cantidad por Porción
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
                                $existingItems = old('items', $recipe->items->map(function($item) {
                                    return [
                                        'ingrediente_id' => $item->ingrediente_id,
                                        'cantidad_por_porcion' => (float) $item->cantidad_por_porcion,
                                    ];
                                })->toArray());
                            @endphp

                            @forelse ($existingItems as $index => $item)
                                <tr class="ingredient-row">
                                    <td>
                                        <select name="items[{{ $index }}][ingrediente_id]" class="form-select ingredient-select" required>
                                            <option value="">Seleccionar insumo...</option>
                                            @foreach ($insumos as $insumo)
                                                <option value="{{ $insumo->id }}" 
                                                        data-unit="{{ $insumo->unidad_base }}" 
                                                        data-name="{{ strtolower($insumo->nombre) }}"
                                                        {{ ($item['ingrediente_id'] ?? '') == $insumo->id ? 'selected' : '' }}>
                                                    {{ $insumo->nombre }} (Stock: {{ $insumo->formatted_stock }} {{ strtolower($insumo->unidad_base) }})
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
                                        <div class="input-group">
                                            <input type="number" 
                                                   name="items[{{ $index }}][cantidad_por_porcion]" 
                                                   class="form-control quantity-input font-monospace" 
                                                   step="0.001" 
                                                   min="0.001" 
                                                   placeholder="0.000"
                                                   value="{{ $item['cantidad_por_porcion'] ?? '' }}" 
                                                   required>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm rounded-circle remove-row-btn" title="Quitar insumo">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                {{-- Fila inicial vacía si la receta aún no tiene items --}}
                                <tr class="ingredient-row">
                                    <td>
                                        <select name="items[0][ingrediente_id]" class="form-select ingredient-select" required>
                                            <option value="">Seleccionar insumo...</option>
                                            @foreach ($insumos as $insumo)
                                                <option value="{{ $insumo->id }}" 
                                                        data-unit="{{ $insumo->unidad_base }}" 
                                                        data-name="{{ strtolower($insumo->nombre) }}">
                                                    {{ $insumo->nombre }} (Stock: {{ $insumo->formatted_stock }} {{ strtolower($insumo->unidad_base) }})
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
                                               name="items[0][cantidad_por_porcion]" 
                                               class="form-control quantity-input font-monospace" 
                                               step="0.001" 
                                               min="0.001" 
                                               placeholder="0.000" 
                                               required>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm rounded-circle remove-row-btn" title="Quitar insumo">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-3 bg-light rounded-3 mb-4 d-flex align-items-center justify-content-between">
                    <span class="small text-muted">
                        <i class="bi bi-info-circle me-1"></i> Cada gramo configurado será deducido automáticamente del inventario al vender este plato.
                    </span>
                    <span class="small fw-semibold text-dark">
                        Total Insumos en la fórmula: <strong id="totalIngredientsCount">0</strong>
                    </span>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('recipes.index') }}" class="btn btn-light rounded-pill px-4 text-secondary">
                        Cancelar
                    </a>
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" id="saveRecipeBtn" style="background-color: #10B981;">
                        <i class="bi bi-check2-circle me-1"></i> Guardar Fórmula de Receta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Template oculto para nuevas filas --}}
<template id="rowTemplate">
    <tr class="ingredient-row">
        <td>
            <select name="items[INDEX][ingrediente_id]" class="form-select ingredient-select" required>
                <option value="">Seleccionar insumo...</option>
                @foreach ($insumos as $insumo)
                    <option value="{{ $insumo->id }}" 
                            data-unit="{{ $insumo->unidad_base }}" 
                            data-name="{{ strtolower($insumo->nombre) }}">
                        {{ $insumo->nombre }} (Stock: {{ $insumo->formatted_stock }} {{ strtolower($insumo->unidad_base) }})
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
                   name="items[INDEX][cantidad_por_porcion]" 
                   class="form-control quantity-input font-monospace" 
                   step="0.001" 
                   min="0.001" 
                   placeholder="0.000" 
                   required>
        </td>
        <td class="text-end">
            <button type="button" class="btn btn-outline-danger btn-sm rounded-circle remove-row-btn" title="Quitar insumo">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('ingredientsBody');
    const addRowBtn = document.getElementById('addRowBtn');
    const rowTemplate = document.getElementById('rowTemplate');
    const exclusionAlert = document.getElementById('exclusionAlert');
    const saveRecipeBtn = document.getElementById('saveRecipeBtn');
    const totalIngredientsCount = document.getElementById('totalIngredientsCount');

    let rowCount = tableBody.querySelectorAll('.ingredient-row').length;

    function updateRowUnits(row) {
        const select = row.querySelector('.ingredient-select');
        const badge = row.querySelector('.unit-badge');
        const selectedOpt = select.options[select.selectedIndex];
        if (selectedOpt && selectedOpt.dataset.unit) {
            badge.textContent = selectedOpt.dataset.unit;
        } else {
            badge.textContent = 'GRAMO';
        }
    }

    function checkMutualExclusion() {
        let hasBeans = false;
        let hasLentils = false;
        const rows = tableBody.querySelectorAll('.ingredient-row');

        rows.forEach(row => {
            const select = row.querySelector('.ingredient-select');
            const qtyInput = row.querySelector('.quantity-input');
            const qty = parseFloat(qtyInput.value) || 0;
            const opt = select.options[select.selectedIndex];

            if (opt && opt.value && qty > 0) {
                const name = (opt.dataset.name || '').toLowerCase();
                if (name.includes('frijol') || name.includes('fríjol')) {
                    hasBeans = true;
                }
                if (name.includes('lenteja')) {
                    hasLentils = true;
                }
            }
        });

        if (hasBeans && hasLentils) {
            exclusionAlert.classList.remove('d-none');
            saveRecipeBtn.disabled = true;
            saveRecipeBtn.classList.add('opacity-50');
        } else {
            exclusionAlert.classList.add('d-none');
            saveRecipeBtn.disabled = false;
            saveRecipeBtn.classList.remove('opacity-50');
        }

        totalIngredientsCount.textContent = rows.length;
    }

    function reindexRows() {
        const rows = tableBody.querySelectorAll('.ingredient-row');
        rows.forEach((row, index) => {
            const select = row.querySelector('.ingredient-select');
            const qty = row.querySelector('.quantity-input');
            select.name = `items[${index}][ingrediente_id]`;
            qty.name = `items[${index}][cantidad_por_porcion]`;
            updateRowUnits(row);
        });
        checkMutualExclusion();
    }

    addRowBtn.addEventListener('click', function () {
        const clone = rowTemplate.content.cloneNode(true);
        const newRow = clone.querySelector('.ingredient-row');
        const select = newRow.querySelector('.ingredient-select');
        const qty = newRow.querySelector('.quantity-input');

        const newIndex = tableBody.querySelectorAll('.ingredient-row').length;
        select.name = `items[${newIndex}][ingrediente_id]`;
        qty.name = `items[${newIndex}][cantidad_por_porcion]`;

        tableBody.appendChild(newRow);
        bindRowEvents(newRow);
        reindexRows();
    });

    function bindRowEvents(row) {
        const select = row.querySelector('.ingredient-select');
        const qtyInput = row.querySelector('.quantity-input');
        const removeBtn = row.querySelector('.remove-row-btn');

        select.addEventListener('change', function () {
            updateRowUnits(row);
            checkMutualExclusion();
        });

        qtyInput.addEventListener('input', checkMutualExclusion);

        removeBtn.addEventListener('click', function () {
            if (tableBody.querySelectorAll('.ingredient-row').length > 1) {
                row.remove();
                reindexRows();
            } else {
                califitNotify('La receta debe contener al menos un insumo.', 'warning', 'Insumo Requerido');
            }
        });
    }

    tableBody.querySelectorAll('.ingredient-row').forEach(row => {
        bindRowEvents(row);
        updateRowUnits(row);
    });

    checkMutualExclusion();
});
</script>
@endpush
@endsection
