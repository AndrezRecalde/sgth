<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\EspecialidadAtencion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaMedicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medico_id'          => ['required', 'integer', 'exists:users,id'],
            'servidor_id'        => ['nullable', 'integer', 'exists:servidores,id'],
            'carga_familiar_id'  => ['nullable', 'integer', 'exists:cargas_familiares,id'],
            'tipo_atencion'      => ['required', Rule::enum(EspecialidadAtencion::class)],
            'motivo_solicitud'   => ['nullable', 'string', 'max:500'],
            'requiere_triaje'    => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tieneServidor      = !empty($this->servidor_id);
            $tieneCargaFamiliar = !empty($this->carga_familiar_id);

            if ($tieneServidor === $tieneCargaFamiliar) {
                $validator->errors()->add(
                    'servidor_id',
                    'Debe indicar exactamente un paciente: ' .
                    'servidor O carga familiar, no ambos ni ninguno.'
                );
            }
        });
    }
}
