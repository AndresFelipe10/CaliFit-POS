<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'real_stock' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Debes seleccionar un producto.',
            'product_id.exists' => 'El producto seleccionado no existe.',
            'real_stock.required' => 'El conteo físico real es obligatorio.',
            'real_stock.min' => 'El conteo físico real no puede ser negativo.',
            'reason.required' => 'El motivo del ajuste de inventario es obligatorio.',
        ];
    }
}
