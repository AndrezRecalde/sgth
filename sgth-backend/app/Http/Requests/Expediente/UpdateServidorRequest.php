<?php

namespace App\Http\Requests\Expediente;

use App\Enums\RegimenLaboral;
use App\Enums\TipoDiscapacidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;

class UpdateServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $servidorId = $this->route('servidore') ?? $this->route('servidor'); // Dependiendo de la definición de la ruta

        return [
            // Identidad base
            'cedula'  => [
                'sometimes', 
                'required', 
                'string', 
                'regex:/^\d{10}$/', 
                Rule::unique('servidores')->ignore($servidorId)
            ],
            'nombre'  => 'sometimes|required|string|max:100',
            'segundo_nombre'   => 'nullable|string|max:100',
            'apellido'         => 'sometimes|required|string|max:100',
            'segundo_apellido' => 'nullable|string|max:100',
            
            // Relaciones y datos base
            'regimen_laboral'          => ['sometimes', 'required', new Enum(RegimenLaboral::class)],
            // puesto_id/unidad_administrativa_id NUNCA se editan aquí: la
            // única vía es ContratoServidorService::sincronizarPuestoDesdeVinculo(),
            // derivado siempre del ContratoServidor vigente. Un cambio de
            // puesto/unidad se hace registrando un MovimientoPersonal
            // (traslado/ascenso/traspaso/cambio_administrativo), no
            // editando el Servidor directamente.
            'unidad_administrativa_id' => ['prohibited'],
            'puesto_id'                => ['prohibited'],

            // Sección A
            'fecha_nacimiento' => 'sometimes|required|date|before:today',
            'genero'           => 'sometimes|required|string|in:masculino,femenino,otro',
            'estado_civil'     => 'sometimes|required|string|in:soltero,casado,union_libre,divorciado,viudo',
            'tipo_sangre'      => 'nullable|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            
            // Extranjería condicional
            'es_extranjero'           => 'sometimes|required|boolean',
            'provincia_nacimiento_id' => 'required_if:es_extranjero,false|nullable|exists:provincias,id',
            'canton_nacimiento_id'    => 'required_if:es_extranjero,false|nullable|exists:cantones,id',
            'nacionalidad'         => 'required_if:es_extranjero,true|nullable|string|max:100',
            'pais_origen'          => 'required_if:es_extranjero,true|nullable|string|max:100',

            // Sección B
            'numero_papeleta_votacion' => 'nullable|string|max:20',
            'pasaporte_numero'         => 'nullable|string|max:50',
            'pasaporte_vencimiento'    => 'nullable|date|after:today',

            // Sección C
            'telefono_celular'      => 'nullable|string|max:20',
            'telefono_convencional' => 'nullable|string|max:20',

            'correo_personal'       => 'nullable|email|max:150',
            'direccion_domicilio'   => 'nullable|string|max:255',

            // Sección D
            'tiene_discapacidad'      => 'sometimes|required|boolean',
            
            // Sección E
            'tiene_enfermedad_catastrofica' => 'sometimes|required|boolean',

            // Sección F
            // tipo_nombramiento tampoco se edita aquí, mismo razonamiento
            // que puesto_id/unidad_administrativa_id: un cambio de
            // modalidad pasa por creaVinculo()/modificaVinculo() al
            // registrar el MovimientoPersonal correspondiente, nunca por
            // un update() directo sobre Servidor.
            'tipo_nombramiento'            => ['prohibited'],
            'numero_contrato'              => 'nullable|string|max:100',
            'fecha_ingreso_institucion'    => 'sometimes|required|date',
            'fecha_ingreso_sector_publico' => 'nullable|date',
            'fecha_nombramiento'           => 'nullable|date',
            'fecha_inicio_ultimo_contrato' => 'nullable|date',
            'fecha_fin_ultimo_contrato'    => 'nullable|date|after:fecha_inicio_ultimo_contrato',

        ];
    }

    public function messages(): array
    {
        return [
            'provincia_nacimiento_id.required_if' => 'La provincia de nacimiento es obligatoria si el servidor no es extranjero.',
            'provincia_nacimiento_id.exists'      => 'La provincia de nacimiento seleccionada no existe en el catálogo.',
            'canton_nacimiento_id.required_if'    => 'El cantón de nacimiento es obligatorio si el servidor no es extranjero.',
            'canton_nacimiento_id.exists'         => 'El cantón de nacimiento seleccionado no existe en el catálogo.',
            'nacionalidad.required_if'            => 'La nacionalidad es obligatoria para servidores extranjeros.',
            'pais_origen.required_if'             => 'El país de origen es obligatorio para servidores extranjeros.',
            
            'cedula.regex'                     => 'La cédula debe contener exactamente 10 dígitos numéricos.',

            'puesto_id.prohibited'                => 'El puesto no se edita aquí: registre un movimiento de traslado, ascenso, traspaso o cambio administrativo.',
            'unidad_administrativa_id.prohibited'  => 'La unidad administrativa no se edita aquí: registre un movimiento de traslado, ascenso, traspaso o cambio administrativo.',
            'tipo_nombramiento.prohibited'         => 'El tipo de nombramiento no se edita aquí: registre el movimiento de personal correspondiente (ingreso, traslado, ascenso, traspaso o cambio administrativo).',

            'fecha_fin_ultimo_contrato.after'  => 'La fecha de fin del contrato debe ser posterior a la fecha de inicio del mismo.',
        ];
    }
}
