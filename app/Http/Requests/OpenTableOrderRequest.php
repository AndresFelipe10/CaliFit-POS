<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenTableOrderRequest extends FormRequest
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
            'guest_count' => 'nullable|integer|min:1|max:99',
            'waiter_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
            'items' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'guest_count.integer' => 'El número de comensales debe ser un número entero.',
            'guest_count.min' => 'El número de comensales debe ser de al menos 1.',
            'guest_count.max' => 'El número de comensales no puede superar 99.',
        ];
    }
}
