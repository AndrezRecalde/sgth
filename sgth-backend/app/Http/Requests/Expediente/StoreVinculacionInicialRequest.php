<?php

namespace App\Http\Requests\Expediente;

use App\Enums\TipoNombramiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Ficha personal + contrato vigente en una sola petición.
 *
 * Reproduce las reglas de StoreServidorBasicoRequest para la ficha y las de
 * StoreContratoServidorRequest para el vínculo, anidadas bajo 'vinculo'. Se
 * duplican a propósito en vez de heredarse: esta es una vía excepcional y
 * temporal, y si mañana cambia una regla del alta ordinaria no debe cambiar en
 * silencio la de la carga histórica.
 */
class StoreVinculacionInicialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Ficha personal ─────────────────────────────────
            'cedula' => ['required', 'string', 'regex:/^\d{10}$/', 'unique:servidores,cedula'],
            'nombre'           => 'required|string|max:100',
            'segundo_nombre'   => 'nullable|string|max:100',
            'apellido'         => 'required|string|max:100',
            'segundo_apellido' => 'nullable|string|max:100',
            'fecha_nacimiento' => 'required|date|before:today',
            'genero'           => 'required|string|in:masculino,femenino,otro',
            'estado_civil'     => 'required|string|in:soltero,casado,union_libre,divorciado,viudo',
            'tipo_sangre'      => 'nullable|string|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'es_extranjero'    => 'required|boolean',
            'provincia_nacimiento_id' => ['required_if:es_extranjero,false', 'nullable', 'exists:provincias,id'],
            'canton_nacimiento_id'    => ['required_if:es_extranjero,false', 'nullable', 'exists:cantones,id'],
            'nacionalidad'            => ['required_if:es_extranjero,true', 'nullable', 'string', 'max:100'],
            'pais_origen'             => ['required_if:es_extranjero,true', 'nullable', 'string', 'max:100'],
            'numero_papeleta_votacion' => 'nullable|string|max:20',
            'pasaporte_numero'         => 'nullable|string|max:50',
            'tiene_discapacidad'            => 'required|boolean',
            'tiene_enfermedad_catastrofica' => 'required|boolean',
            'telefono_celular'      => 'nullable|string|max:20',
            'telefono_convencional' => 'nullable|string|max:20',
            'correo_personal'       => 'nullable|email|max:150',
            'direccion_domicilio'   => 'nullable|string|max:255',

            // Primera vinculación con la institución. De aquí sale la
            // antigüedad; si no viene, el servicio usa la del contrato.
            'fecha_ingreso_institucion'    => 'nullable|date|before_or_equal:today',
            'fecha_ingreso_sector_publico' => 'nullable|date|before_or_equal:today',

            // ── Contrato vigente ───────────────────────────────
            'vinculo'                            => 'required|array',
            'vinculo.tipo_nombramiento'          => ['required', new Enum(TipoNombramiento::class)],
            'vinculo.unidad_administrativa_id'   => 'required|exists:unidades_administrativas,id',
            'vinculo.puesto_id'                  => 'required|exists:puestos,id',
            'vinculo.fecha_inicio'               => 'required|date|before_or_equal:today',
            'vinculo.fecha_fin'                  => 'nullable|date|after:vinculo.fecha_inicio',
            'vinculo.remuneracion'               => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'vinculo.numero_contrato'            => 'nullable|string|max:100',
            'vinculo.resolucion_numero'          => 'nullable|string|max:100',
            'vinculo.puede_marcar'               => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'cedula.unique' => 'Esta cédula ya está registrada. Si el servidor existe, registre su vínculo desde su expediente.',
            'cedula.regex'  => 'La cédula debe tener 10 dígitos numéricos.',
            'vinculo.required' => 'La vinculación inicial exige registrar el contrato vigente del servidor.',
            'vinculo.remuneracion.required' => 'La remuneración es obligatoria: es la que consta hoy en el rol de pagos.',
            'vinculo.fecha_inicio.before_or_equal' => 'La carga inicial es para vínculos que ya existen: la fecha de inicio no puede ser futura.',
        ];
    }

    /** @return array<string, mixed> */
    public function datosServidor(): array
    {
        return collect($this->validated())->except('vinculo')->all();
    }

    /** @return array<string, mixed> */
    public function datosVinculo(): array
    {
        return $this->validated()['vinculo'];
    }
}
