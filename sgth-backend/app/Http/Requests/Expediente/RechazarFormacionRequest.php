<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/** El rechazo de un título o curso registrado desde el portal: siempre con motivo. */
class RechazarFormacionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Diga por qué se rechaza: el servidor lo verá en su perfil.',
        ];
    }
}
