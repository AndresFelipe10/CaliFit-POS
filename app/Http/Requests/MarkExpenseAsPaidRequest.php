<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkExpenseAsPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'paid_at' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,card,transfer,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_at.required' => 'La fecha de pago es obligatoria.',
            'payment_method.required' => 'Debe seleccionar un método de pago.',
            'payment_method.in' => 'El método de pago no es válido.',
        ];
    }
}
