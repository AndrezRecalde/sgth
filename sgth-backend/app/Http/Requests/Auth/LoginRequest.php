<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario'    => ['required', 'string'],
            'contrasena' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario.required'    => 'El nombre de usuario es obligatorio.',
            'contrasena.required' => 'La contraseña es obligatoria.',
        ];
    }
}
