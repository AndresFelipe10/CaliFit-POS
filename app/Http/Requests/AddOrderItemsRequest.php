<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddOrderItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001|max:999',
            'items.*.notes' => 'nullable|string|max:255',
            'items.*.additions' => 'nullable|array',
            'items.*.additions.*.addition_id' => 'required_with:items.*.additions|exists:adiciones,id',
            'items.*.additions.*.quantity' => 'required_with:items.*.additions|numeric|min:1|max:99',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debes incluir al menos un producto o plato en la comanda.',
            'items.min' => 'Debes incluir al menos un producto o plato en la comanda.',
            'items.*.product_id.exists' => 'Uno de los productos seleccionados no existe.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a 0.',
        ];
    }
}
