<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

class StoreServidorBasicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cedula'           => [
                'required', 'string',
                'regex:/^\d{10}$/',
                'unique:servidores,cedula',
            ],
            'nombre'           => 'required|string|max:100',
            'segundo_nombre'   => 'nullable|string|max:100',
            'apellido'         => 'required|string|max:100',
            'segundo_apellido' => 'nullable|string|max:100',
            'fecha_nacimiento' => 'required|date|before:today',
            'genero'           => 'required|string|in:masculino,femenino,otro',
            'estado_civil'     => 'required|string|in:soltero,casado,union_libre,divorciado,viudo',
            'tipo_sangre'      => 'nullable|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'es_extranjero'    => 'required|boolean',
            'provincia_nacimiento_id' => [
                'required_if:es_extranjero,false',
                'nullable',
                'exists:provincias,id',
            ],
            'canton_nacimiento_id' => [
                'required_if:es_extranjero,false',
                'nullable',
                'exists:cantones,id',
            ],
            'nacionalidad' => [
                'required_if:es_extranjero,true',
                'nullable',
                'string',
                'max:100',
            ],
            'pais_origen' => [
                'required_if:es_extranjero,true',
                'nullable',
                'string',
                'max:100',
            ],
            'numero_papeleta_votacion' => 'nullable|string|max:20',
            'pasaporte_numero'         => 'nullable|string|max:50',
            'tiene_discapacidad'           => 'required|boolean',
            'tiene_enfermedad_catastrofica' => 'required|boolean',
            // Contacto opcional
            'telefono_celular'      => 'nullable|string|max:20',
            'telefono_convencional' => 'nullable|string|max:20',
            'correo_personal'       => 'nullable|email|max:150',
            'direccion_domicilio'   => 'nullable|string|max:255',
            'provincia_domicilio'   => 'nullable|string|max:100',
            'ciudad_domicilio'      => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'cedula.required'  => 'La cédula es obligatoria.',
            'cedula.regex'     => 'La cédula debe tener 10 dígitos numéricos.',
            'cedula.unique'    => 'Esta cédula ya está registrada.',
            'nombre.required'  => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'provincia_nacimiento_id.required_if' =>
                'La provincia de nacimiento es obligatoria.',
            'canton_nacimiento_id.required_if' =>
                'El cantón de nacimiento es obligatorio.',
            'nacionalidad.required_if' =>
                'La nacionalidad es obligatoria para servidores extranjeros.',
            'pais_origen.required_if' =>
                'El país de origen es obligatorio para servidores extranjeros.',
        ];
    }
}
