<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class CancelRestaurantOrderItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'El motivo de cancelación del producto es obligatorio por auditoría.',
            'cancellation_reason.string' => 'El motivo de cancelación debe ser un texto válido.',
            'cancellation_reason.min' => 'El motivo de cancelación debe tener al menos 5 caracteres.',
            'cancellation_reason.max' => 'El motivo de cancelación no puede exceder los 500 caracteres.',
        ];
    }
}
