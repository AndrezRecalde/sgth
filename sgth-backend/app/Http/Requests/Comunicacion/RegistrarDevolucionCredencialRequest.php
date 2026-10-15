<?php

namespace App\Http\Requests\Comunicacion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La tarjeta impresa volvió a Comunicación Social (docs/diseno-comunicacion-social.md,
 * 6.5): el paso «Devolución de la credencial» de la desvinculación.
 */
class RegistrarDevolucionCredencialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'nota'  => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha de la devolución',
            'nota'  => 'nota',
        ];
    }
}
