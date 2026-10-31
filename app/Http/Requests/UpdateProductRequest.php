<?php

namespace App\Http\Requests;

use App\Traits\NormalizesCurrencyInputs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    use NormalizesCurrencyInputs;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (! $this->has('nombre') && $this->has('name')) {
            $merge['nombre'] = $this->input('name');
        }
        if ($this->has('precio_compra')) {
            $merge['precio_compra'] = $this->normalizeCurrency($this->input('precio_compra'));
        }
        if ($this->has('precio_venta')) {
            $merge['precio_venta'] = $this->normalizeCurrency($this->input('precio_venta'));
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $productParam = $this->route('product');
        $productId = $productParam instanceof \App\Models\Product ? $productParam->id : $productParam;

        return [
            'nombre' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku,' . $productId],
            'tipo_producto' => ['required', 'in:INSUMO,PREPARACION,PLATO,SUPLEMENTO'],
            'unidad_base' => ['required', 'in:UNIDAD,GRAMO,MILILITRO'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'precio_compra' => ['nullable', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'sku.required' => 'El SKU o código identificador es obligatorio.',
            'sku.unique' => 'Este SKU ya está registrado en otro producto.',
            'tipo_producto.required' => 'Debe clasificar el tipo de producto.',
            'unidad_base.required' => 'Debe seleccionar una unidad base.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $tipo = $this->input('tipo_producto');
                $unidad = $this->input('unidad_base');

                if ($tipo === 'SUPLEMENTO' && $unidad !== 'UNIDAD') {
                    $validator->errors()->add('unidad_base', 'Los suplementos deportivos deben tener como unidad base UNIDAD.');
                }

                if ($tipo === 'INSUMO' && ! in_array($unidad, ['GRAMO', 'MILILITRO'], true)) {
                    $validator->errors()->add('unidad_base', 'Los insumos de cocina deben tener como unidad base GRAMO o MILILITRO.');
                }
            },
        ];
    }
}
