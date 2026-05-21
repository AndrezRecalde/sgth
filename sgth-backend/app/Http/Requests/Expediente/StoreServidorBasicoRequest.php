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
            'user_id'  => 'required|exists:users,id|unique:servidores,user_id',
            'cedula'   => [
                'required',
                'string',
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
            'es_extranjero'    => 'required|boolean',
            'provincia_nacimiento_id' => 'required_if:es_extranjero,false|nullable|exists:provincias,id',
            'canton_nacimiento_id'    => 'required_if:es_extranjero,false|nullable|exists:cantones,id',
            'nacionalidad'    => 'required_if:es_extranjero,true|nullable|string|max:100',
            'pais_origen'     => 'required_if:es_extranjero,true|nullable|string|max:100',
            'tiene_discapacidad'           => 'required|boolean',
            'tiene_enfermedad_catastrofica' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'  => 'Seleccione el usuario del sistema.',
            'user_id.exists'    => 'El usuario seleccionado no existe.',
            'user_id.unique'    => 'Este usuario ya tiene un expediente.',
            'cedula.regex'      => 'La cédula debe tener 10 dígitos.',
            'cedula.unique'     => 'Esta cédula ya está registrada.',
            'nombre.required'   => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'provincia_nacimiento_id.required_if' =>
                'La provincia de nacimiento es obligatoria.',
            'canton_nacimiento_id.required_if' =>
                'El cantón de nacimiento es obligatorio.',
        ];
    }
}
