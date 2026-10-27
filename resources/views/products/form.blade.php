@extends('layouts.app')

@section('title', $isEdit ? 'Editar Producto: ' . $product->nombre : 'Nuevo Producto en Catálogo')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <a href="{{ route('products.index') }}" class="text-decoration-none text-muted small fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Volver al Catálogo
                </a>
                <h3 class="fw-bold mb-0 text-dark mt-1">
                    {{ $isEdit ? 'Editar Producto' : 'Registrar Nuevo Producto' }}
                </h3>
            </div>
            @if($isEdit && $product->tipo_producto === 'PLATO')
                <a href="{{ route('recipes.edit', $product) }}" class="btn btn-outline-success rounded-pill px-3 fw-semibold">
                    <i class="bi bi-journal-text me-1"></i> Configurar Receta Base
                </a>
            @endif
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
            <form action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}" method="POST" id="productForm">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                <div class="row g-3">
                    {{-- Tipo de Producto --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark small">Tipo de Producto <span class="text-danger">*</span></label>
                        <select name="tipo_producto" id="tipo_producto" class="form-select @error('tipo_producto') is-invalid @enderror" required>
                            <option value="PLATO" {{ old('tipo_producto', $product->tipo_producto) === 'PLATO' ? 'selected' : '' }}>
                                Plato de Cocina / Carta (Alimentación Saludable)
                            </option>
                            <option value="SUPLEMENTO" {{ old('tipo_producto', $product->tipo_producto) === 'SUPLEMENTO' ? 'selected' : '' }}>
                                Suplemento Deportivo (Proteínas, Creatinas, etc.)
                            </option>
                            <option value="INSUMO" {{ old('tipo_producto', $product->tipo_producto) === 'INSUMO' ? 'selected' : '' }}>
                                Insumo / Materia Prima (Gramos / Mililitros)
                            </option>
                            <option value="PREPARACION" {{ old('tipo_producto', $product->tipo_producto) === 'PREPARACION' ? 'selected' : '' }}>
                                Preparación Intermedia / Bebida
                            </option>
                        </select>
                        <div class="form-text small" id="tipoHelp">
                            Define la naturaleza contable e inventariable del producto en SaborCaliFit.
                        </div>
                    </div>

                    {{-- Unidad Base --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark small">Unidad de Medida Base <span class="text-danger">*</span></label>
                        <select name="unidad_base" id="unidad_base" class="form-select @error('unidad_base') is-invalid @enderror" required>
                            <option value="UNIDAD" {{ old('unidad_base', $product->unidad_base) === 'UNIDAD' ? 'selected' : '' }}>UNIDAD (Und)</option>
                            <option value="GRAMO" {{ old('unidad_base', $product->unidad_base) === 'GRAMO' ? 'selected' : '' }}>GRAMO (g)</option>
                            <option value="MILILITRO" {{ old('unidad_base', $product->unidad_base) === 'MILILITRO' ? 'selected' : '' }}>MILILITRO (ml)</option>
                        </select>
                        <div class="form-text small" id="unidadHelp">
                            Suplementos usan obligatoriamente UNIDAD. Insumos operan en GRAMO o MILILITRO.
                        </div>
                    </div>

                    {{-- SKU y Categoría --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark small">Código / SKU <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted font-monospace"><i class="bi bi-upc-scan"></i></span>
                            <input type="text" 
                                   name="sku" 
                                   id="sku" 
                                   class="form-control font-monospace text-uppercase @error('sku') is-invalid @enderror" 
                                   placeholder="Ej: SUP-001 o PLA-005"
                                   value="{{ old('sku', $product->sku ?? '') }}" 
                                   required 
                                   maxlength="50">
                        </div>
                        @error('sku')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold text-dark small">Categoría</label>
                        <select name="category_id" id="category_id" class="form-select @error('category_id') is-invalid @enderror">
                            <option value="">Sin Categoría Específica</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id) == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Nombre --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark small">Nombre Comercial del Producto <span class="text-danger">*</span></label>
                        <input type="text" 
                               name="nombre" 
                               id="nombre" 
                               class="form-control @error('nombre') is-invalid @enderror" 
                               placeholder="Ej: Bowl Proteico de Pollo 150g o Creatina Monohidratada 300g"
                               value="{{ old('nombre', $product->nombre ?? '') }}" 
                               required 
                               maxlength="150">
                        @error('nombre')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Precios y Stock Mínimo --}}
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-dark small">Precio de Compra (Costo COP)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">$</span>
                            <input type="text" 
                                   inputmode="numeric"
                                   name="precio_compra" 
                                   id="precio_compra" 
                                   class="form-control currency-input @error('precio_compra') is-invalid @enderror" 
                                   placeholder="0" 
                                   value="{{ old('precio_compra', ($product->precio_compra ?? 0) ? number_format((float)$product->precio_compra, 0, ',', '.') : '0') }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-dark small">Precio de Venta (PVP COP) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted">$</span>
                            <input type="text" 
                                   inputmode="numeric"
                                   name="precio_venta" 
                                   id="precio_venta" 
                                   class="form-control fw-bold text-success currency-input @error('precio_venta') is-invalid @enderror" 
                                   placeholder="0" 
                                   value="{{ old('precio_venta', ($product->precio_venta ?? 0) ? number_format((float)$product->precio_venta, 0, ',', '.') : '0') }}" 
                                   required>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-dark small">Stock Mínimo de Alerta <span class="text-danger">*</span></label>
                        <input type="number" 
                               name="stock_minimo" 
                               id="stock_minimo" 
                               step="0.001" 
                               min="0" 
                               class="form-control @error('stock_minimo') is-invalid @enderror" 
                               value="{{ old('stock_minimo', (float)($product->stock_minimo ?? 5)) }}" 
                               required>
                    </div>

                    {{-- Indicador de Margen en Vivo --}}
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="small text-muted">
                                <i class="bi bi-calculator me-1"></i> Margen Bruto Estimado:
                                <span class="fw-bold text-dark fs-6 ms-1" id="margenValor">$0</span>
                            </div>
                            <div class="small">
                                Rentabilidad sobre costo: <span class="badge bg-emerald text-white px-2 py-1" id="margenPorcentaje" style="background-color: #10B981;">0%</span>
                            </div>
                        </div>
                    </div>

                    {{-- Descripción --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark small">Descripción o Ficha Técnica</label>
                        <textarea name="descripcion" 
                                  id="descripcion" 
                                  rows="3" 
                                  class="form-control @error('descripcion') is-invalid @enderror" 
                                  placeholder="Detalles nutricionales, tamaño de porción, modo de uso o advertencias...">{{ old('descripcion', $product->descripcion ?? '') }}</textarea>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('products.index') }}" class="btn btn-light rounded-pill px-4 text-secondary">
                        Cancelar
                    </a>
                    <button type="submit" class="btn text-white rounded-pill px-4 fw-semibold shadow-sm" style="background-color: #10B981;">
                        <i class="bi bi-check2-circle me-1"></i> {{ $isEdit ? 'Actualizar Producto' : 'Guardar Producto' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect = document.getElementById('tipo_producto');
    const unidadSelect = document.getElementById('unidad_base');
    const precioCompra = document.getElementById('precio_compra');
    const precioVenta = document.getElementById('precio_venta');
    const margenValor = document.getElementById('margenValor');
    const margenPorcentaje = document.getElementById('margenPorcentaje');

    function syncUnidades() {
        const tipo = tipoSelect.value;
        if (tipo === 'SUPLEMENTO') {
            unidadSelect.value = 'UNIDAD';
            // Deshabilitar gramo y mililitro
            Array.from(unidadSelect.options).forEach(opt => {
                opt.disabled = (opt.value !== 'UNIDAD');
            });
        } else if (tipo === 'INSUMO') {
            if (unidadSelect.value === 'UNIDAD') {
                unidadSelect.value = 'GRAMO';
            }
            Array.from(unidadSelect.options).forEach(opt => {
                opt.disabled = (opt.value === 'UNIDAD');
            });
        } else {
            Array.from(unidadSelect.options).forEach(opt => {
                opt.disabled = false;
            });
        }
    }

    function calculateMargin() {
        const cost = parseFloat(precioCompra.value) || 0;
        const sale = parseFloat(precioVenta.value) || 0;
        const diff = sale - cost;

        margenValor.textContent = '$' + new Intl.NumberFormat('es-CO').format(diff);

        if (cost > 0) {
            const pct = ((diff / cost) * 100).toFixed(1);
            margenPorcentaje.textContent = pct + '%';
        } else {
            margenPorcentaje.textContent = sale > 0 ? '100%' : '0%';
        }
    }

    tipoSelect.addEventListener('change', syncUnidades);
    precioCompra.addEventListener('input', calculateMargin);
    precioVenta.addEventListener('input', calculateMargin);

    syncUnidades();
    calculateMargin();
});
</script>
@endpush
@endsection
