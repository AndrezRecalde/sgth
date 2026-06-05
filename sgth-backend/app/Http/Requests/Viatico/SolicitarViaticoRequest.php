<?php
namespace App\Http\Requests\Viatico;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarViaticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'zona' => [
                'required', 'string',
                'in:dentro_provincia,fuera_provincia,exterior',
            ],
            'justificacion' => [
                'required', 'string', 'min:10', 'max:2000',
            ],
            'modalidad_anticipo' => [
                'required',
                'in:sin_anticipo,total,parcial',
            ],
            'tipo_viaje'  => ['nullable', 'string', 'max:100'],
            'pais_destino'=> ['nullable', 'string', 'max:100'],
            'monto_calculado' => ['nullable', 'numeric', 'min:0'],
            'servidores_acompanantes'   => ['nullable', 'array'],
            'servidores_acompanantes.*' => ['integer', 'exists:servidores,id'],
        ];

        // Para exterior el monto es obligatorio
        if ($this->input('zona') === 'exterior') {
            $rules['monto_calculado'] = [
                'required', 'numeric', 'min:1',
            ];
        }

        // Para anticipo parcial el monto es obligatorio
        if ($this->input('modalidad_anticipo') === 'parcial') {
            $rules['monto_calculado'] = [
                'required', 'numeric', 'min:1',
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'zona.in'                   => 'La zona especificada no es válida.',
            'zona.required'             => 'La zona geográfica es obligatoria.',
            'justificacion.required'    => 'Debe justificar el motivo del viaje.',
            'justificacion.min'         => 'La justificación debe tener al menos 10 caracteres.',
            'modalidad_anticipo.required'=> 'La modalidad de anticipo es obligatoria.',
            'modalidad_anticipo.in'     => 'La modalidad de anticipo no es válida.',
            'monto_calculado.required'  => 'El monto es obligatorio para viajes al exterior o anticipos parciales.',
            'monto_calculado.min'       => 'El monto debe ser mayor a cero.',
        ];
    }
}
