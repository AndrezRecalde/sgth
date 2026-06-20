<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoParentesco;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCargaFamiliarRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'cedula'      => [
                'required', 'string', 'size:10',
                'unique:cargas_familiares,cedula',
            ],
            'apellidos'                     => ['required', 'string', 'max:100'],
            'nombres'                       => ['required', 'string', 'max:100'],
            'parentesco'                    => ['required', new Enum(TipoParentesco::class)],
            'fecha_nacimiento'              => ['required', 'date', 'before:today'],
            'persona_con_discapacidad'      => ['required', 'boolean'],
            'posee_enfermedad_catastrofica' => ['required', 'boolean'],
            'observaciones'                 => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'cedula.required' => 'La cédula del familiar es obligatoria.',
            'cedula.size'     => 'La cédula debe tener 10 dígitos.',
            'cedula.unique'   => 'Esta cédula ya está registrada como carga familiar.',
        ];
    }
}