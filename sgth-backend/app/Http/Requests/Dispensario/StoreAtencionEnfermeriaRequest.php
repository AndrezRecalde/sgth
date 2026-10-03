<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAtencionEnfermeriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Un servicio retirado del catálogo no se ofrece en la pantalla, y un
        // paciente borrado en blando ya no existe para el dispensario: `exists`
        // a secas aceptaba los dos.
        return [
            'servidor_id' => [
                'nullable', 'integer',
                Rule::exists('servidores', 'id')->whereNull('deleted_at'),
            ],
            'carga_familiar_id' => [
                'nullable', 'integer',
                Rule::exists('cargas_familiares', 'id')->whereNull('deleted_at'),
            ],
            'catalogo_servicio_id' => [
                'required', 'integer',
                Rule::exists('catalogo_servicios_enfermeria', 'id')->where('activo', true),
            ],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'catalogo_servicio_id.exists' => 'El servicio elegido ya no está disponible en el catálogo.',
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
