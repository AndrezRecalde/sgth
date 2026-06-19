<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHistoriaClinicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servidor_id'         => ['nullable', 'integer', 'exists:servidores,id'],
            'beneficiario_id'     => ['nullable', 'integer', 'exists:beneficiarios,id'],
            'grupo_sanguineo'     => ['nullable', 'string', 'max:5'],
            'medicacion_habitual' => ['nullable', 'string', 'max:1000'],
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
