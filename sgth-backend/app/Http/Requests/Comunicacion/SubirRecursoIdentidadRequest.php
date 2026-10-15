<?php

namespace App\Http\Requests\Comunicacion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Una versión nueva de una variante del logo. Que sea PNG o JPG, sus medidas y
 * su proporción dependen de la variante: las revisa
 * IdentidadInstitucionalService, que da el motivo con las medidas reales.
 */
class SubirRecursoIdentidadRequest extends FormRequest
{
    public const PESO_MAXIMO_KB = 5120;

    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archivo'       => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:'.self::PESO_MAXIMO_KB],
            'vigente_desde' => ['nullable', 'date'],
            'nota'          => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'archivo'       => 'archivo',
            'vigente_desde' => 'fecha desde la que rige',
            'nota'          => 'nota',
        ];
    }
}
