<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del colaborador es obligatorio.',
            'name.min' => 'El nombre del colaborador debe tener al menos 3 caracteres.',
            'name.max' => 'El nombre del colaborador no puede exceder 100 caracteres.',
            'name.regex' => 'El nombre del colaborador solo debe contener letras y espacios.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.unique' => 'Ya existe un usuario registrado con este correo.',
            'role_id.required' => 'Debe asignar un rol válido al colaborador.',
        ];
    }
}
