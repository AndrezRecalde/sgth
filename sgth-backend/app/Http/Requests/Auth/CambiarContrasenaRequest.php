<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class CambiarContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // En este punto el usuario debe estar autenticado
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nueva_contrasena' => [
                'required', 
                'string', 
                'min:8', 
                'regex:/[a-zA-Z]/', // Al menos una letra
                'regex:/[0-9]/'     // Al menos un número
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nueva_contrasena.required' => 'La nueva contraseña es obligatoria.',
            'nueva_contrasena.min'      => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'nueva_contrasena.regex'    => 'La nueva contraseña debe contener letras y números.',
        ];
    }
}
