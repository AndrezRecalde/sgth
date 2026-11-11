<?php

namespace App\Http\Requests\Estructura;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A qué unidad de hoy corresponde una gestión del Manual de Puestos (P4,
 * PR 12). Sin unidad, vuelve a la sugerida. El permiso lo pide la ruta.
 */
class GuardarEquivalenciaManualRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'gestion'                  => ['required', 'string', 'max:300'],
            'unidad_administrativa_id' => ['present', 'nullable', 'integer', Rule::exists('unidades_administrativas', 'id')->where('estado', true)],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'gestion'                  => 'gestión del manual',
            'unidad_administrativa_id' => 'unidad',
        ];
    }
}
