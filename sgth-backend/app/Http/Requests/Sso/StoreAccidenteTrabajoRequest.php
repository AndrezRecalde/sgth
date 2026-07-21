<?php

namespace App\Http\Requests\Sso;

use App\Enums\TipoEventoAccidente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreAccidenteTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Sso\AccidenteTrabajo::class);
    }

    public function rules(): array
    {
        return [
            'servidor_id'              => ['required', 'integer', 'exists:servidores,id'],
            'tipo_evento'              => ['required', new Enum(TipoEventoAccidente::class)],
            'fecha_accidente'          => ['required', 'date', 'before_or_equal:today'],
            'hora_accidente'           => ['required', 'date_format:H:i'],
            'lugar_accidente'          => ['required', 'string', 'max:255'],
            'descripcion_hechos'       => ['required', 'string', 'max:3000'],
            'gravedad'                 => ['required', 'string', 'max:50'],
            'requirio_atencion_medica' => ['boolean'],
            'dias_reposo_medico'       => ['nullable', 'integer', 'min:0'],
            'causa_raiz'               => ['nullable', 'string', 'max:2000'],
            'medidas_correctivas'      => ['nullable', 'string', 'max:2000'],
            'estado'                   => ['boolean'],
            'investigado_por'          => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
