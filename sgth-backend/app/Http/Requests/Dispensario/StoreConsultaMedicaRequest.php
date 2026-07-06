<?php
namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsultaMedicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'historia_clinica_id'     => ['required', 'integer', 'exists:historias_clinicas,id'],
            'agenda_medica_id'        => ['nullable', 'integer', 'exists:agendas_medicas,id'],
            'fecha_consulta'          => ['required', 'date'],
            'hora_consulta'           => ['required', 'date_format:H:i'],
            'tipo_atencion'           => ['required', Rule::in([
                'primera_vez', 'subsecuente', 'interconsulta'
            ])],
            'tipo_diagnostico'        => ['required', Rule::in([
                'presuntivo', 'definitivo'
            ])],
            'motivo_consulta'         => ['required', 'string', 'max:2000'],
            'enfermedad_actual'       => ['nullable', 'string', 'max:5000'],
            'examen_fisico'           => ['nullable', 'string', 'max:2000'],
            'diagnostico_cie10_id'    => ['nullable', 'integer', 'exists:diagnosticos_cie10,id'],
            'diagnostico_detallado'   => ['required', 'string', 'max:2000'],
            'diagnosticos_secundarios'=> ['nullable', 'array', 'max:3'],
            'diagnosticos_secundarios.*' => ['integer', 'exists:diagnosticos_cie10,id'],
            'plan_tratamiento'        => ['nullable', 'string', 'max:2000'],
            'notas_medico'            => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'required'             => 'El campo :attribute es obligatorio.',
            'exists'               => 'El valor seleccionado para :attribute no es válido.',
            'tipo_atencion.in'     => 'Tipo de atención inválido.',
            'tipo_diagnostico.in'  => 'Tipo de diagnóstico inválido.',
            'diagnosticos_secundarios.max' => 'Máximo 3 diagnósticos secundarios.',
        ];
    }
}
