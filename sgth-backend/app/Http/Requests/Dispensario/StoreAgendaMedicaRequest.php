<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgendaMedicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medico_id'        => ['required', 'integer', 'exists:users,id'],
            'servidor_id'      => ['nullable', 'integer', 'exists:servidores,id'],
            'beneficiario_id'  => ['nullable', 'integer', 'exists:beneficiarios,id'],
            'fecha'            => ['required', 'date'],
            'hora_inicio'      => ['required', 'date_format:H:i'],
            'hora_fin'         => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'motivo_solicitud' => ['required', 'string', 'max:500'],
            'requiere_triaje'  => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tieneServidor     = !empty($this->servidor_id);
            $tieneBeneficiario = !empty($this->beneficiario_id);

            if ($tieneServidor === $tieneBeneficiario) {
                $validator->errors()->add(
                    'servidor_id',
                    'Debe indicar exactamente un paciente: ' .
                    'servidor O beneficiario, no ambos ni ninguno.'
                );
            }
        });
    }
}
