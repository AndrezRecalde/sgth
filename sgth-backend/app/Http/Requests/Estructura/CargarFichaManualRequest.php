<?php

namespace App\Http\Requests\Estructura;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El puesto en que se carga una ficha del Manual de Puestos (P4, PR 12). El
 * permiso, `gestionar-puestos`, lo pide la ruta.
 */
class CargarFichaManualRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'puesto_id' => ['required', 'integer', Rule::exists('puestos', 'id')->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['puesto_id' => 'puesto'];
    }
}
