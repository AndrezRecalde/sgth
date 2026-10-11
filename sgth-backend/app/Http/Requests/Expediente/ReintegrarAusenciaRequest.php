<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El reintegro que se prepara desde la ausencia (fase 2.4): cuándo vuelve el
 * servidor y la explicación del documento. El rango de la fecha lo valida
 * ReintegroService, que conoce la ausencia.
 */
class ReintegrarAusenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_regreso' => ['required', 'date'],
            'descripcion'   => ['required', 'string', 'max:1000'],
            'observacion'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_regreso' => 'fecha de regreso',
        ];
    }
}
