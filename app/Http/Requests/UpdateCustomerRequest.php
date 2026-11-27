<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['document', 'document_number', 'phone', 'telefono', 'email', 'address', 'direccion', 'name', 'nombre'] as $field) {
            if ($this->has($field)) {
                $val = $this->input($field);
                $trimmed = is_string($val) ? trim($val) : $val;
                $merge[$field] = ($trimmed === '' || $trimmed === null) ? null : $trimmed;
            }
        }

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $customerParam = $this->route('customer');
        $customerId = $customerParam instanceof \App\Models\Customer ? $customerParam->id : $customerParam;

        return [
            'nombre' => ['required_without:name', 'nullable', 'string', 'min:3', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'name' => ['required_without:nombre', 'nullable', 'string', 'min:3', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'document' => ['nullable', 'string', 'regex:/^[0-9]{5,12}$/', Rule::unique('customers', 'document')->ignore($customerId), 'not_in:222222222222'],
            'document_number' => ['nullable', 'string', 'regex:/^[0-9]{5,12}$/', Rule::unique('customers', 'document')->ignore($customerId), 'not_in:222222222222'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'regex:/^[0-9]{7,10}$/'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{7,10}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required_without' => 'El nombre del cliente es obligatorio.',
            'name.required_without' => 'El nombre del cliente es obligatorio.',
            'nombre.min' => 'El nombre del cliente debe tener al menos 3 caracteres.',
            'name.min' => 'El nombre del cliente debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre del cliente no puede exceder 100 caracteres.',
            'name.max' => 'El nombre del cliente no puede exceder 100 caracteres.',
            'nombre.regex' => 'El nombre del cliente solo debe contener letras y espacios.',
            'name.regex' => 'El nombre del cliente solo debe contener letras y espacios.',
            'document.required' => 'El documento de identidad es obligatorio.',
            'document.regex' => 'El documento del cliente debe ser numérico y contener entre 5 y 12 dígitos.',
            'document_number.regex' => 'El documento del cliente debe ser numérico y contener entre 5 y 12 dígitos.',
            'document.unique' => 'Ya existe otro cliente registrado con este documento.',
            'document_number.unique' => 'Ya existe otro cliente registrado con este documento.',
            'document.not_in' => 'El documento 222222222222 está reservado para Consumidor Final DIAN y no puede registrarse como cliente.',
            'document_number.not_in' => 'El documento 222222222222 está reservado para Consumidor Final DIAN y no puede registrarse como cliente.',
            'telefono.regex' => 'El teléfono debe ser numérico y contener entre 7 y 10 dígitos.',
            'phone.regex' => 'El teléfono debe ser numérico y contener entre 7 y 10 dígitos.',
            'email.email' => 'El correo electrónico ingresado no tiene un formato válido.',
        ];
    }

    public function validatedCustomerData(): array
    {
        $data = $this->validated();
        $name = trim((string) ($data['nombre'] ?? $data['name'] ?? ''));
        $rawDoc = $data['document'] ?? $data['document_number'] ?? null;
        $doc = ($rawDoc !== null && trim((string) $rawDoc) !== '') ? trim((string) $rawDoc) : null;
        $phone = $data['telefono'] ?? $data['phone'] ?? null;
        $address = $data['direccion'] ?? $data['address'] ?? null;

        return [
            'name' => $name,
            'document' => $doc,
            'email' => $data['email'] ?? null,
            'phone' => ($phone !== null && trim((string) $phone) !== '') ? trim((string) $phone) : null,
            'address' => ($address !== null && trim((string) $address) !== '') ? trim((string) $address) : null,
        ];
    }
}
