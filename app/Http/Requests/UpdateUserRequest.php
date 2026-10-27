<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $userParam = $this->route('user');
        $userId = $userParam instanceof \App\Models\User ? $userParam->id : $userParam;

        return [
            'name' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $userId],
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
            'email.unique' => 'Este correo ya pertenece a otro usuario registrado.',
            'role_id.required' => 'Debe asignar un rol válido al colaborador.',
        ];
    }
}
