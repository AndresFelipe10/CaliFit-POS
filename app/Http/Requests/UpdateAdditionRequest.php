<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Traits\NormalizesCurrencyInputs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAdditionRequest extends FormRequest
{
    use NormalizesCurrencyInputs;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('precio')) {
            $this->merge(['precio' => $this->normalizeCurrency($this->input('precio'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'precio' => ['required', 'numeric', 'min:0'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.ingrediente_id' => ['required', 'distinct', 'exists:products,id'],
            'ingredients.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'dish_ids' => ['nullable', 'array'],
            'dish_ids.*' => ['exists:products,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la adición es obligatorio.',
            'precio.required' => 'El precio de la adición es obligatorio.',
            'ingredients.required' => 'La adición debe consumir al menos un insumo.',
            'ingredients.min' => 'La adición debe consumir al menos un insumo.',
            'ingredients.*.ingrediente_id.required' => 'Debe seleccionar un insumo.',
            'ingredients.*.ingrediente_id.distinct' => 'No puede repetir el mismo ingrediente más de una vez en la adición.',
            'ingredients.*.cantidad.required' => 'Debe indicar la cantidad de insumo consumida.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $dishIds = $this->input('dish_ids', []);
                if (! empty($dishIds)) {
                    $invalidDishes = Product::whereIn('id', $dishIds)
                        ->where('tipo_producto', '!=', 'PLATO')
                        ->count();

                    if ($invalidDishes > 0) {
                        $validator->errors()->add('dish_ids', 'Las adiciones solo pueden asociarse a productos de tipo PLATO.');
                    }
                }

                $ingredients = $this->input('ingredients', []);
                if (! empty($ingredients) && is_array($ingredients)) {
                    $ingIds = array_filter(array_column($ingredients, 'ingrediente_id'));
                    if (! empty($ingIds)) {
                        $invalidProducts = Product::whereIn('id', $ingIds)
                            ->where('tipo_producto', '!=', 'INSUMO')
                            ->get(['id', 'nombre'])
                            ->keyBy('id');

                        if ($invalidProducts->isNotEmpty()) {
                            $validator->errors()->add(
                                'ingredients',
                                'Los ingredientes de la adición deben ser exclusivamente de tipo INSUMO. Productos no válidos: ' . $invalidProducts->pluck('nombre')->implode(', ') . '.'
                            );

                            foreach ($ingredients as $idx => $ing) {
                                $pId = $ing['ingrediente_id'] ?? null;
                                if ($pId && $invalidProducts->has($pId)) {
                                    $validator->errors()->add(
                                        "ingredients.{$idx}.ingrediente_id",
                                        "El producto '{$invalidProducts[$pId]->nombre}' no es de tipo INSUMO."
                                    );
                                }
                            }
                        }
                    }
                }
            },
        ];
    }
}
