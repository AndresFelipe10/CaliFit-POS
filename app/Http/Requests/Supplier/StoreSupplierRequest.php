<?php

namespace App\Http\Requests\Supplier;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Supplier::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'identification_number' => ['nullable', 'string', 'min:5', 'max:15', 'regex:/^\d{5,15}(-\d)?$/'],
            'contact_name' => ['nullable', 'string', 'min:2', 'max:80', 'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.\'-]+$/u'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'min:7', 'max:15', 'regex:/^\+?[0-9]{7,15}$/'],
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

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la empresa o razón social es obligatorio.',
            'name.min' => 'El nombre del proveedor debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre del proveedor no puede superar los 100 caracteres.',
            'identification_number.min' => 'El NIT o documento debe tener al menos 5 dígitos.',
            'identification_number.max' => 'El NIT o documento no puede superar los 15 caracteres.',
            'identification_number.regex' => 'El NIT o documento debe contener entre 5 y 15 dígitos numéricos (con guion y dígito de verificación opcional).',
            'contact_name.min' => 'El nombre de contacto debe tener al menos 2 caracteres.',
            'contact_name.max' => 'El nombre de contacto no puede superar los 80 caracteres.',
            'contact_name.regex' => 'El nombre de contacto solo debe contener letras, espacios, puntos o guiones.',
            'phone.min' => 'El teléfono debe tener al menos 7 dígitos.',
            'phone.max' => 'El teléfono no puede superar los 15 dígitos.',
            'phone.regex' => 'El teléfono debe contener únicamente números (entre 7 y 15 dígitos) y opcionalmente el prefijo +.',
            'address.min' => 'La dirección debe tener al menos 5 caracteres.',
            'address.max' => 'La dirección no puede superar los 150 caracteres.',
            'address.regex' => 'La dirección contiene caracteres no válidos o repetición indebida de caracteres.',
        ];
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if ($phone !== null) {
            $phone = trim((string)$phone);
            if ($phone === '') {
                $phone = null;
            } elseif (!preg_match('/[a-zA-Z]/', $phone)) {
                $phone = preg_replace('/[\s\-()]/', '', $phone);
            }
        }

        $address = $this->input('address');
        if ($address !== null && trim((string)$address) === '') {
            $address = null;
        }

        $contactName = $this->input('contact_name');
        if ($contactName !== null && trim((string)$contactName) === '') {
            $contactName = null;
        }

        $nit = $this->input('identification_number');
        if ($nit !== null && trim((string)$nit) === '') {
            $nit = null;
        }

        $email = $this->input('email');
        if ($email !== null && trim((string)$email) === '') {
            $email = null;
        }

        $this->merge([
            'phone' => $phone,
            'address' => $address,
            'contact_name' => $contactName,
            'identification_number' => $nit,
            'email' => $email,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
