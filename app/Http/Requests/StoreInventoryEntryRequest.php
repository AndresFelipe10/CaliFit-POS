<?php

namespace App\Http\Requests;

use App\Traits\NormalizesCurrencyInputs;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryEntryRequest extends FormRequest
{
    use NormalizesCurrencyInputs;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cost_price')) {
            $this->merge(['cost_price' => $this->normalizeCurrency($this->input('cost_price'))]);
        }
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
            'purchase_unit' => 'required|in:gramo,libra,kilo,litro,mililitro,unidad',
            'purchase_quantity' => 'required|numeric|min:0.001',
            'cost_price' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'invoice_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Debes seleccionar un producto.',
            'product_id.exists' => 'El producto seleccionado no existe.',
            'purchase_unit.required' => 'La unidad de compra es obligatoria.',
            'purchase_unit.in' => 'La unidad de compra debe ser gramo, libra, kilo, litro, mililitro o unidad.',
            'purchase_quantity.required' => 'La cantidad comprada es obligatoria.',
            'purchase_quantity.min' => 'La cantidad comprada debe ser mayor a 0.',
        ];
    }
}
