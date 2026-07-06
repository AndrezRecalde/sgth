<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsultaMedicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_atencion'              => ['required', Rule::in([
                'primera_vez', 'subsecuente', 'interconsulta'
            ])],
            'tipo_diagnostico'           => ['required', Rule::in([
                'presuntivo', 'definitivo'
            ])],
            'motivo_consulta'            => ['required', 'string', 'max:2000'],
            'enfermedad_actual'          => ['nullable', 'string', 'max:5000'],
            'examen_fisico'              => ['nullable', 'string', 'max:2000'],
            'diagnostico_cie10_id'       => ['nullable', 'integer', 'exists:diagnosticos_cie10,id'],
            'diagnostico_detallado'      => ['required', 'string', 'max:5000'],
            'diagnosticos_secundarios'   => ['nullable', 'array', 'max:3'],
            'diagnosticos_secundarios.*' => ['integer', 'exists:diagnosticos_cie10,id'],
            'plan_tratamiento'           => ['nullable', 'string', 'max:5000'],
            'notas_medico'               => ['nullable', 'string', 'max:2000'],
        ];
    }
}
