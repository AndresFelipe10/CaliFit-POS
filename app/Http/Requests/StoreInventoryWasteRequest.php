<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryWasteRequest extends FormRequest
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
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Debes seleccionar un producto.',
            'product_id.exists' => 'El producto seleccionado no existe.',
            'quantity.required' => 'La cantidad de merma es obligatoria.',
            'quantity.min' => 'La cantidad de merma debe ser mayor a 0.',
            'reason.required' => 'El motivo de la merma o pérdida es obligatorio.',
        ];
    }
}
