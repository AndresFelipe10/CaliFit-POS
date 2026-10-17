<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $this->user()->can('update', $customer);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = session('current_business_id');
        $customer = $this->route('customer');

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                'regex:/[a-zA-ZáéíóúÁÉÍÓÚñÑ]/',
            ],
            'document' => [
                'required',
                'string',
                'min:5',
                'max:15',
                'regex:/^\d{5,15}(-\d)?$/',
                'not_in:222222222222',
                Rule::unique('customers', 'document')
                    ->where('business_id', $businessId)
                    ->ignore($customer ? $customer->id : null),
            ],
            'identification_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'min:7', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'address' => [
                'nullable',
                'string',
                'min:5',
                'max:150',
                'regex:/^(?!.*(.)\1{5,})[a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s#\-\.,\/°]+$/u',
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom message for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre o razón social es obligatorio.',
            'name.min' => 'El nombre o razón social debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre o razón social no puede superar los 100 caracteres.',
            'name.regex' => 'El nombre o razón social debe contener letras y no solo números.',
            'document.required' => 'El documento o NIT es obligatorio.',
            'document.min' => 'El documento debe contener al menos 5 dígitos.',
            'document.max' => 'El documento no puede superar los 15 caracteres.',
            'document.regex' => 'El documento debe contener entre 5 y 15 dígitos numéricos (con guión y dígito opcional para NIT).',
            'document.not_in' => 'El documento 222222222222 está reservado para Consumidor Final de la DIAN.',
            'document.unique' => 'Ya existe un cliente registrado con este documento en este negocio.',
            'phone.min' => 'El teléfono debe tener al menos 7 dígitos.',
            'phone.max' => 'El teléfono no puede superar los 20 caracteres.',
            'phone.regex' => 'El teléfono debe contener únicamente números (entre 7 y 15 dígitos) y opcionalmente el prefijo +.',
            'address.min' => 'La dirección debe tener al menos 5 caracteres.',
            'address.max' => 'La dirección no puede superar los 150 caracteres.',
            'address.regex' => 'La dirección contiene caracteres no válidos o repetición indebida de caracteres.',
            'email.email' => 'El campo correo electrónico debe ser una dirección de correo válida.',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $doc = $this->input('document') ?? $this->input('identification_number');

        if ($doc !== null) {
            $doc = trim((string) $doc);
            $this->merge([
                'document' => $doc,
                'identification_number' => $doc,
            ]);
        }

        $phone = $this->input('phone');
        if ($phone !== null) {
            $phone = trim((string)$phone);
            if ($phone === '') {
                $phone = null;
            }
        }

        $address = $this->input('address');
        if ($address !== null && trim((string)$address) === '') {
            $address = null;
        }

        $email = $this->input('email');
        if ($email !== null && trim((string)$email) === '') {
            $email = null;
        }

        $this->merge([
            'phone' => $phone,
            'address' => $address,
            'email' => $email,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
