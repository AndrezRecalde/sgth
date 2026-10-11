<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El reintegro del ascendido que no superó la prueba (fase 3.3): cuándo vuelve
 * a su puesto anterior, el informe de la evaluación y la explicación del
 * documento. El rango de la fecha lo valida ReintegroService, que conoce el
 * período de prueba.
 */
class ReintegrarAscensoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_regreso'       => ['required', 'date'],
            // La ley lo condiciona a una evaluación técnica y objetiva (LOSEP 17 b.5).
            'informe_uath_numero' => ['required', 'string', 'max:100'],
            'descripcion'         => ['required', 'string', 'max:1000'],
            'observacion'         => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_regreso'       => 'fecha de regreso',
            'informe_uath_numero' => 'número del informe de la evaluación',
        ];
    }
}
