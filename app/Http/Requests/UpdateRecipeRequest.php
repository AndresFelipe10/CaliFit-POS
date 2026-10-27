<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingrediente_id' => ['required', 'distinct', 'exists:products,id'],
            'items.*.cantidad_por_porcion' => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debe agregar al menos un ingrediente a la receta.',
            'items.min' => 'La receta debe tener al menos un ingrediente configurado.',
            'items.*.ingrediente_id.required' => 'Debe seleccionar un insumo válido.',
            'items.*.ingrediente_id.distinct' => 'No puede repetir el mismo ingrediente más de una vez en la receta.',
            'items.*.ingrediente_id.exists' => 'El insumo seleccionado no existe.',
            'items.*.cantidad_por_porcion.required' => 'Debe ingresar la cantidad por porción.',
            'items.*.cantidad_por_porcion.min' => 'La porción debe ser mayor a 0.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
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
                        $validator->errors()->add("items.{$index}.ingrediente_id", "El producto '{$product->nombre}' no es de tipo INSUMO.");
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
