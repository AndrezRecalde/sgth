<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsultaMedicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'historia_clinica_id'   => ['required', 'integer', 'exists:historias_clinicas,id'],
            'agenda_medica_id'      => ['nullable', 'integer', 'exists:agendas_medicas,id'],
            'fecha_consulta'        => ['required', 'date'],
            'hora_consulta'         => ['required', 'date_format:H:i'],
            'motivo_consulta'       => ['required', 'string', 'max:2000'],
            'examen_fisico'         => ['nullable', 'string', 'max:2000'],
            'diagnostico_detallado' => ['required', 'string', 'max:2000'],
            'diagnostico_cie10'     => ['nullable', 'integer', 'exists:diagnosticos_cie10,id'],
            'plan_tratamiento'      => ['nullable', 'string', 'max:2000'],
            'notas_medico'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'exists'   => 'El valor seleccionado para :attribute no es válido.',
        ];
    }
}
