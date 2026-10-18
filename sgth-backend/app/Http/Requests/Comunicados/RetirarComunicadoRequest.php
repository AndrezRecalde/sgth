<?php

namespace App\Http\Requests\Comunicados;

use Illuminate\Foundation\Http\FormRequest;

/** Retirar lo publicado: el motivo queda en el historial (docs/diseno-noticias-comunicados.md, regla 3). */
class RetirarComunicadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['motivo' => 'motivo'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['motivo.required' => 'Diga por qué se retira la publicación.'];
    }
}
