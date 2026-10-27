<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'nit' => ['required', 'string', 'regex:/^[0-9-]+$/', 'max:30', 'unique:suppliers,nit'],
            'contact_name' => ['nullable', 'string', 'min:3', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{7,10}$/'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'La razón social o nombre del proveedor es obligatorio.',
            'nit.required' => 'El NIT o documento tributario es obligatorio.',
            'nit.regex' => 'El NIT solo debe contener números y guiones.',
            'nit.unique' => 'Ya existe un proveedor registrado con este NIT.',
            'contact_name.regex' => 'El nombre del contacto solo debe contener letras y espacios.',
            'phone.regex' => 'El teléfono debe ser numérico y contener entre 7 y 10 dígitos.',
            'email.email' => 'El correo electrónico ingresado no tiene un formato válido.',
        ];
    }
}
