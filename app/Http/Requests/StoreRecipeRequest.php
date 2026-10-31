<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $rules = [
            'dish_mode' => ['required', 'in:existing,new'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingrediente_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.cantidad_por_porcion' => ['required', 'numeric', 'min:0.001'],
        ];

        if ($this->input('dish_mode') === 'existing') {
            $rules['product_id'] = ['required', 'exists:products,id'];
        } else {
            $rules['nombre'] = ['required', 'string', 'max:150'];
            $rules['sku'] = ['nullable', 'string', 'max:50', 'unique:products,sku'];
            $rules['category_id'] = ['required', 'exists:categories,id'];
            $rules['precio_venta'] = ['required', 'numeric', 'min:0'];
            $rules['descripcion'] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'dish_mode.required' => 'Debe indicar si la receta es para un plato existente o nuevo.',
            'product_id.required' => 'Debe seleccionar un plato existente de la carta.',
            'product_id.exists' => 'El plato seleccionado no existe en el catálogo.',
            'nombre.required' => 'El nombre del plato saludable es obligatorio.',
            'sku.unique' => 'El código SKU ingresado ya está asignado a otro producto.',
            'category_id.required' => 'Debe asociar una categoría para el plato.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'precio_venta.required' => 'El precio de venta en carta es obligatorio.',
            'precio_venta.min' => 'El precio de venta no puede ser negativo.',
            'items.required' => 'Debe agregar al menos un ingrediente por porción a la receta.',
            'items.min' => 'La receta debe contener al menos un ingrediente configurado.',
            'items.*.ingrediente_id.required' => 'Debe seleccionar un insumo válido.',
            'items.*.ingrediente_id.distinct' => 'No puede repetir el mismo ingrediente en la receta.',
            'items.*.ingrediente_id.exists' => 'El insumo seleccionado no existe.',
            'items.*.cantidad_por_porcion.required' => 'Debe ingresar la cantidad por porción.',
            'items.*.cantidad_por_porcion.min' => 'La cantidad por porción debe ser mayor a 0.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Si es plato existente, validar que sea PLATO
                if ($this->input('dish_mode') === 'existing') {
                    $prodId = $this->input('product_id');
                    if ($prodId) {
                        $dish = Product::find($prodId);
                        if ($dish && $dish->tipo_producto !== 'PLATO') {
                            $validator->errors()->add('product_id', "El producto seleccionado ('{$dish->nombre}') no es de tipo PLATO.");
                        }
                    }
                }

                $rawItems = $this->input('items', []);
                if (! is_array($rawItems) || empty($rawItems)) {
                    return;
                }

                $ingIds = array_filter(array_column($rawItems, 'ingrediente_id'));
                if (empty($ingIds)) {
                    return;
                }

                $products = Product::whereIn('id', $ingIds)->get()->keyBy('id');

                $hasFrijoles = false;
                $hasLentejas = false;

                foreach ($rawItems as $index => $item) {
                    $prodId = $item['ingrediente_id'] ?? null;
                    $qty = (float) ($item['cantidad_por_porcion'] ?? 0);

                    if (! $prodId || ! isset($products[$prodId])) {
                        continue;
                    }

                    $product = $products[$prodId];

                    if ($product->tipo_producto !== 'INSUMO') {
                        $validator->errors()->add("items.{$index}.ingrediente_id", "El ingrediente '{$product->nombre}' no es de tipo INSUMO.");
                    }

                    if ($qty > 0) {
                        $nombreLower = mb_strtolower($product->nombre);
                        if (str_contains($nombreLower, 'frijol')) {
                            $hasFrijoles = true;
                        }
                        if (str_contains($nombreLower, 'lenteja')) {
                            $hasLentejas = true;
                        }
                    }
                }

                if ($hasFrijoles && $hasLentejas) {
                    $validator->errors()->add('items', 'Una receta base no puede incluir frijoles y lentejas simultáneamente (regla de exclusión mutua de SaborCaliFit).');
                }
            },
        ];
    }
}

