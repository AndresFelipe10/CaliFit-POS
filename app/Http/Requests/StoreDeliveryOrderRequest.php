<?php

namespace App\Http\Requests;

use App\Traits\NormalizesCurrencyInputs;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryOrderRequest extends FormRequest
{
    use NormalizesCurrencyInputs;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('order_type') === 'takeout') {
            $this->merge([
                'delivery_fee' => 0.00,
                'address' => null,
            ]);
        } elseif ($this->has('delivery_fee')) {
            $this->merge(['delivery_fee' => $this->normalizeCurrency($this->input('delivery_fee'))]);
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
            'order_type' => 'required|in:delivery,takeout',
            'customer_name' => 'required|string|max:150',
            'phone' => 'required|string|max:30',
            'address' => 'required_if:order_type,delivery|nullable|string|max:255',
            'delivery_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',

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
            'order_type.required' => 'El tipo de pedido (domicilio o para llevar) es obligatorio.',
            'customer_name.required' => 'El nombre del cliente es obligatorio.',
            'phone.required' => 'El número de teléfono/WhatsApp es obligatorio.',
            'address.required_if' => 'La dirección de entrega es obligatoria para pedidos a domicilio.',
            'items.required' => 'Debes incluir al menos un producto o plato en el pedido.',
            'items.min' => 'Debes incluir al menos un producto o plato en el pedido.',
        ];
    }
}
