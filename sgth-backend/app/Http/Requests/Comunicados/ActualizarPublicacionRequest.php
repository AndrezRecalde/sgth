<?php

namespace App\Http\Requests\Comunicados;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lo que cambia en lo publicado: el fijado y la fecha de fin
 * (docs/diseno-noticias-comunicados.md, regla 1). Se manda solo lo que cambia.
 */
class ActualizarPublicacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fijado'          => ['sometimes', 'boolean'],
            'publicado_hasta' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'fijado'          => 'fijado',
            'publicado_hasta' => 'fecha de fin',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['publicado_hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a hoy.'];
    }
}
