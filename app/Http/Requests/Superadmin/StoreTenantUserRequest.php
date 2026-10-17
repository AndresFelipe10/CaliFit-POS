<?php

namespace App\Http\Requests\Superadmin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_superadmin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:80',
                'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.\'-]+$/u',
            ],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:admin,employee'],
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre completo del colaborador es obligatorio.',
            'name.min' => 'El nombre del colaborador debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre no puede superar los 80 caracteres.',
            'name.regex' => 'El nombre del colaborador solo debe contener letras, espacios, puntos o guiones.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico es inválido.',
            'email.max' => 'El correo no puede superar los 255 caracteres.',
            'email.unique' => 'Ese correo ya está registrado en la plataforma.',
            'role.required' => 'Debes seleccionar un rol para el colaborador.',
            'role.in' => 'El rol seleccionado no es válido (debe ser Administrador o Empleado).',
            'password.min' => 'La contraseña temporal debe tener al menos 8 caracteres.',
        ];
    }
}
