<?php

namespace App\Http\Requests\Expediente;

use App\Enums\RegimenLaboral;
use App\Enums\TipoDiscapacidad;
use App\Enums\TipoNombramiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Identidad base
            'user_id' => 'required|exists:users,id|unique:servidores,user_id',
            'cedula'  => [
                'required',
                'string',
                'regex:/^\d{10}$/',
                'unique:servidores,cedula',
            ],
            'nombre'  => 'required|string|max:100',
            'segundo_nombre'   => 'nullable|string|max:100',
            'apellido'         => 'required|string|max:100',
            'segundo_apellido' => 'nullable|string|max:100',
            
            // Relaciones y datos base
            'regimen_laboral'          => ['required', new Enum(RegimenLaboral::class)],
            'unidad_administrativa_id' => 'required|exists:unidades_administrativas,id',
            'puesto_id'                => 'required|exists:puestos,id',
            
            // Sección A — Datos personales completos
            'fecha_nacimiento' => 'required|date|before:today',
            'genero'           => 'required|string|in:masculino,femenino,otro',
            'estado_civil'     => 'required|string|in:soltero,casado,union_libre,divorciado,viudo',
            'tipo_sangre'      => 'nullable|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            
            // Extranjería condicional
            'es_extranjero'        => 'required|boolean',
            'provincia_nacimiento' => 'required_if:es_extranjero,false|nullable|string|max:100',
            'ciudad_nacimiento'    => 'required_if:es_extranjero,false|nullable|string|max:100',
            'nacionalidad'         => 'required_if:es_extranjero,true|nullable|string|max:100',
            'pais_origen'          => 'required_if:es_extranjero,true|nullable|string|max:100',

            // Sección B — Documentos
            'numero_papeleta_votacion' => 'nullable|string|max:20',
            'pasaporte_numero'         => 'nullable|string|max:50',
            'pasaporte_vencimiento'    => 'nullable|date|after:today',

            // Sección C — Contacto
            'telefono_celular'      => 'nullable|string|max:20',
            'telefono_convencional' => 'nullable|string|max:20',
            'correo_institucional'  => 'nullable|email|max:150|unique:servidores,correo_institucional',
            'correo_personal'       => 'nullable|email|max:150',
            'direccion_domicilio'   => 'nullable|string|max:255',
            'provincia_domicilio'   => 'nullable|string|max:100',
            'ciudad_domicilio'      => 'nullable|string|max:100',

            // Sección D — Discapacidad condicional
            'tiene_discapacidad'      => 'required|boolean',
            'tipo_discapacidad'       => ['required_if:tiene_discapacidad,true', 'nullable', new Enum(TipoDiscapacidad::class)],
            'porcentaje_discapacidad' => 'required_if:tiene_discapacidad,true|nullable|numeric|min:0.01|max:100.00',
            'numero_carnet_conadis'   => 'required_if:tiene_discapacidad,true|nullable|string|max:50',
            
            // Sección E — Enfermedad catastrófica
            'tiene_enfermedad_catastrofica' => 'required|boolean',
            'tipo_enfermedad_catastrofica'  => 'required_if:tiene_enfermedad_catastrofica,true|nullable|string|max:255',

            // Sección F — Laboral
            'tipo_nombramiento'            => ['required', new Enum(TipoNombramiento::class)],
            'numero_contrato'              => 'nullable|string|max:100',
            'fecha_ingreso_institucion'    => 'required|date',
            'fecha_ingreso_sector_publico' => 'nullable|date',
            'fecha_nombramiento'           => 'nullable|date',
            'fecha_inicio_ultimo_contrato' => 'nullable|date',
            'fecha_fin_ultimo_contrato'    => 'nullable|date|after:fecha_inicio_ultimo_contrato',

            // Sección G — Biométrico
            'codigo_marcacion' => 'nullable|string|regex:/^[A-Za-z0-9]{10}$/|unique:servidores,codigo_marcacion',
        ];
    }

    public function messages(): array
    {
        return [
            'provincia_nacimiento.required_if' => 'La provincia de nacimiento es obligatoria si el servidor no es extranjero.',
            'ciudad_nacimiento.required_if'    => 'La ciudad de nacimiento es obligatoria si el servidor no es extranjero.',
            'nacionalidad.required_if'         => 'La nacionalidad es obligatoria para servidores extranjeros.',
            'pais_origen.required_if'          => 'El país de origen es obligatorio para servidores extranjeros.',
            
            'tipo_discapacidad.required_if'    => 'Debe especificar el tipo de discapacidad.',
            'porcentaje_discapacidad.required_if' => 'Debe especificar el porcentaje de discapacidad.',
            'porcentaje_discapacidad.min'      => 'El porcentaje de discapacidad debe ser mayor a 0.',
            'porcentaje_discapacidad.max'      => 'El porcentaje de discapacidad no puede ser mayor a 100.',
            'numero_carnet_conadis.required_if'=> 'El número de carnet del CONADIS es obligatorio al declarar discapacidad.',
            
            'tipo_enfermedad_catastrofica.required_if' => 'Debe especificar el nombre de la enfermedad catastrófica.',
            
            'codigo_marcacion.regex'           => 'El código de marcación debe contener exactamente 10 caracteres alfanuméricos.',
            'codigo_marcacion.unique'          => 'El código de marcación biométrica ya se encuentra asignado a otro servidor.',
            
            'cedula.regex'                     => 'La cédula debe contener exactamente 10 dígitos numéricos.',
            
            'fecha_fin_ultimo_contrato.after'  => 'La fecha de fin del contrato debe ser posterior a la fecha de inicio del mismo.',
        ];
    }
}
