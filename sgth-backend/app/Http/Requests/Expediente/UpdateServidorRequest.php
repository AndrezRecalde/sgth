<?php

namespace App\Http\Requests\Expediente;

use App\Enums\Permiso;
use App\Enums\TipoDiscapacidad;
use App\Models\Expediente\Servidor;
use App\Models\Geografia\Canton;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Lo que el titular puede cambiar de su propia ficha. ServidorPolicy le
     * deja actualizarla y delega aquí qué campos: hasta el 2026-09-19 esta
     * lista no existía y un servidor podía cambiarse la cédula, el régimen o
     * la fecha de ingreso —y con ella sus días de vacaciones—.
     */
    private const CAMPOS_DEL_TITULAR = [
        'telefono_celular',
        'telefono_convencional',
        'correo_personal',
        'direccion_domicilio',
        // Es contacto: el asistente lo corrige como los teléfonos.
        'contacto_emergencia_nombre',
        'contacto_emergencia_parentesco',
        'contacto_emergencia_telefono',
    ];

    public function rules(): array
    {
        $reglas = $this->reglas();

        // Quien puede crear fichas (Talento Humano) edita todo; el titular,
        // solo su contacto.
        if ($this->user()->can('crear', Servidor::class)) {
            return $reglas;
        }

        foreach (array_keys($reglas) as $campo) {
            if (! in_array($campo, self::CAMPOS_DEL_TITULAR, true)) {
                $reglas[$campo] = ['prohibited'];
            }
        }

        return $reglas;
    }

    private function reglas(): array
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
            // El régimen sale del contrato vigente (sincronizarRegimenServidor),
            // como el tipo de nombramiento: escrito a mano en la ficha quedaba
            // diciendo otra cosa que el vínculo (fase 1.5; diseño, 8.3).
            'regimen_laboral'          => ['prohibited'],
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
            // Registro profesional ante el ACESS. Solo lo tiene el personal
            // de salud; aparece en la sección O de la ficha FEMO que firma.
            'codigo_medico'         => 'nullable|string|max:30',
            'direccion_domicilio'   => 'nullable|string|max:255',
            // Contacto de emergencia, opcional. Nombre y teléfono van juntos:
            // uno sin el otro no sirve para llamar a nadie.
            // Aquí sin `required_with`: el modal envía solo lo que cambió, y
            // corregir el nombre sin reenviar el teléfono ya guardado no
            // debe fallar. Se comprueba contra la ficha en after().
            'contacto_emergencia_nombre'     => 'nullable|string|max:150',
            'contacto_emergencia_parentesco' => 'nullable|string|max:50',
            'contacto_emergencia_telefono'   => 'nullable|string|max:20',

            // Secciones D y E: las dos marcas se derivan de los registros de
            // discapacidad y enfermedad del expediente (pestaña Condición),
            // así que aquí no se escriben.
            'tiene_discapacidad'            => ['prohibited'],
            'tiene_enfermedad_catastrofica' => ['prohibited'],

            // Sección F
            // tipo_nombramiento tampoco se edita aquí, mismo razonamiento
            // que puesto_id/unidad_administrativa_id: un cambio de
            // modalidad pasa por creaVinculo()/modificaVinculo() al
            // registrar el MovimientoPersonal correspondiente, nunca por
            // un update() directo sobre Servidor.
            'tipo_nombramiento'            => ['prohibited'],
            // Sale de la historia de vínculos desde la fase 1.5 (diseño, 8.3).
            // Corregirla a mano —un error de la carga inicial— pide su propio
            // permiso, y el cambio queda en la auditoría de la ficha.
            'fecha_ingreso_institucion'    => $this->user()->can(Permiso::CORREGIR_DATOS_LABORALES->value)
                ? 'sometimes|required|date|before_or_equal:today'
                : ['prohibited'],
            'fecha_ingreso_sector_publico' => 'nullable|date',
            'fecha_nombramiento'           => 'nullable|date',
            // Aquí estaban numero_contrato y las fechas del último contrato:
            // esas columnas se borraron de servidores (viven en el vínculo) y
            // lo validado se descartaba sin avisar.

        ];
    }

    /**
     * El cantón tiene que ser de la provincia: el backend aceptaba cualquier
     * cantón del catálogo, y al cambiar solo la provincia el cantón viejo se
     * quedaba en la ficha apuntando a otra.
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validarContactoDeEmergencia($validator), function (Validator $validator) {
            if ($validator->errors()->hasAny(['provincia_nacimiento_id', 'canton_nacimiento_id'])) {
                return;
            }
            if (! $this->has('provincia_nacimiento_id') && ! $this->has('canton_nacimiento_id')) {
                return;
            }

            $actual = Servidor::find($this->route('servidore') ?? $this->route('servidor'));
            $provincia = $this->has('provincia_nacimiento_id')
                ? $this->input('provincia_nacimiento_id')
                : $actual?->provincia_nacimiento_id;
            $canton = $this->has('canton_nacimiento_id')
                ? $this->input('canton_nacimiento_id')
                : $actual?->canton_nacimiento_id;

            if ($canton && $provincia
                && ! Canton::whereKey($canton)->where('provincia_id', $provincia)->exists()
            ) {
                $validator->errors()->add(
                    'canton_nacimiento_id',
                    'El cantón de nacimiento no pertenece a la provincia elegida.',
                );
            }
        }];
    }

    /**
     * Nombre y teléfono de emergencia van juntos, contando lo que ya tiene la
     * ficha: uno sin el otro no sirve para llamar a nadie.
     */
    private function validarContactoDeEmergencia(Validator $validator): void
    {
        $campos = ['contacto_emergencia_nombre', 'contacto_emergencia_telefono'];
        if (! $this->hasAny($campos) || $validator->errors()->hasAny($campos)) {
            return;
        }

        $actual = Servidor::find($this->route('servidore') ?? $this->route('servidor'));
        $valor = fn (string $campo) => $this->has($campo) ? $this->input($campo) : $actual?->{$campo};
        [$nombre, $telefono] = [$valor($campos[0]), $valor($campos[1])];

        if (filled($nombre) && blank($telefono)) {
            $validator->errors()->add($campos[1], 'Indique el teléfono del contacto de emergencia.');
        } elseif (filled($telefono) && blank($nombre)) {
            $validator->errors()->add($campos[0], 'Indique el nombre del contacto de emergencia.');
        }
    }

    public function messages(): array
    {
        return [
            // El asistente también es de Talento Humano: el mensaje anterior le
            // decía que solo podía cambiarlo Talento Humano.
            'prohibited' => 'Este dato solo lo puede cambiar el administrador de Talento Humano.',
            'provincia_nacimiento_id.required_if' => 'La provincia de nacimiento es obligatoria si el servidor no es extranjero.',
            'provincia_nacimiento_id.exists'      => 'La provincia de nacimiento seleccionada no existe en el catálogo.',
            'canton_nacimiento_id.required_if'    => 'El cantón de nacimiento es obligatorio si el servidor no es extranjero.',
            'canton_nacimiento_id.exists'         => 'El cantón de nacimiento seleccionado no existe en el catálogo.',
            'nacionalidad.required_if'            => 'La nacionalidad es obligatoria para servidores extranjeros.',
            'pais_origen.required_if'             => 'El país de origen es obligatorio para servidores extranjeros.',
            
            'cedula.regex'                     => 'La cédula debe contener exactamente 10 dígitos numéricos.',
            // Sin él llegaba «validation.unique»: no hay traducciones en lang/.
            'cedula.unique'                    => 'Esta cédula ya está registrada en otro expediente.',

            'puesto_id.prohibited'                => 'El puesto no se edita aquí: registre un movimiento de traslado, ascenso, traspaso o cambio administrativo.',
            'unidad_administrativa_id.prohibited'  => 'La unidad administrativa no se edita aquí: registre un movimiento de traslado, ascenso, traspaso o cambio administrativo.',
            'tiene_discapacidad.prohibited' => 'La discapacidad se registra en la pestaña Condición del expediente.',
            'tiene_enfermedad_catastrofica.prohibited' => 'La enfermedad catastrófica se registra en la pestaña Condición del expediente.',

            'tipo_nombramiento.prohibited'         => 'El tipo de nombramiento no se edita aquí: registre el movimiento de personal correspondiente (ingreso, traslado, ascenso, traspaso o cambio administrativo).',

            'fecha_fin_ultimo_contrato.after'  => 'La fecha de fin del contrato debe ser posterior a la fecha de inicio del mismo.',
        ];
    }
}
