<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;

class StoreAtencionEnfermeriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servidor_id'          => ['nullable', 'integer', 'exists:servidores,id'],
            'carga_familiar_id'    => ['nullable', 'integer', 'exists:cargas_familiares,id'],
            'catalogo_servicio_id' => ['required', 'integer', 'exists:catalogo_servicios_enfermeria,id'],
            'descripcion'          => ['nullable', 'string', 'max:1000'],
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
