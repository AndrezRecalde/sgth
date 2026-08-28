<?php

namespace App\Http\Resources\Expediente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El arreglo va enumerado campo por campo, no con `parent::toArray()`.
 *
 * Scramble solo infiere la forma de un recurso cuando `toArray()` devuelve un
 * arreglo literal: ante `parent::toArray()` se rinde y emite
 * `ServidorResource: unknown[]`, que en TypeScript no es un objeto sino un
 * arreglo de nada. De ahí venía que `types/api.ts` tuviera que sostener a mano
 * un `ServidorConRelaciones` paralelo y que cada consumidor terminara
 * casteando. Si agregas una columna a `servidores`, agrégala también aquí.
 */
class ServidorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'cedula'           => $this->cedula,
            'nombre'           => $this->nombre,
            'segundo_nombre'   => $this->segundo_nombre,
            'apellido'         => $this->apellido,
            'segundo_apellido' => $this->segundo_apellido,

            'regimen_laboral'          => $this->regimen_laboral,
            'unidad_administrativa_id' => $this->unidad_administrativa_id,
            'puesto_id'                => $this->puesto_id,
            // Estado propio del servidor (activo/inactivo). No confundir con
            // el del contrato ni con `pendiente_vinculacion`.
            'estado'                   => $this->estado,

            // ── Sección A · identidad ───────────────────────────────
            'fecha_nacimiento'        => $this->fecha_nacimiento,
            'genero'                  => $this->genero,
            'estado_civil'            => $this->estado_civil,
            'tipo_sangre'             => $this->tipo_sangre,
            'es_extranjero'           => $this->es_extranjero,
            'nacionalidad'            => $this->nacionalidad,
            'pais_origen'             => $this->pais_origen,
            'provincia_nacimiento_id' => $this->provincia_nacimiento_id,
            'canton_nacimiento_id'    => $this->canton_nacimiento_id,

            // ── Sección B · documentos de identidad ─────────────────
            'numero_papeleta_votacion' => $this->numero_papeleta_votacion,
            'pasaporte_numero'         => $this->pasaporte_numero,
            'pasaporte_vencimiento'    => $this->pasaporte_vencimiento,

            // ── Sección C · contacto ────────────────────────────────
            'telefono_celular'      => $this->telefono_celular,
            'telefono_convencional' => $this->telefono_convencional,
            'correo_personal'       => $this->correo_personal,
            'direccion_domicilio'   => $this->direccion_domicilio,

            // ── Sección O · datos del profesional ───────────────────
            // Registro ante el ACESS de quien evalúa: lo pide el impreso
            // SNS-MSP/HCU-form.123/2025 junto a su nombre. Es de la persona,
            // no del usuario ni de la ficha.
            'codigo_medico' => $this->codigo_medico,

            // ── Secciones D y E · condiciones de salud ──────────────
            'tiene_discapacidad'            => $this->tiene_discapacidad,
            'tiene_enfermedad_catastrofica' => $this->tiene_enfermedad_catastrofica,

            // ── Sección F · vínculo y antigüedad ────────────────────
            'tipo_nombramiento'            => $this->tipo_nombramiento,
            'fecha_ingreso_institucion'    => $this->fecha_ingreso_institucion,
            'fecha_ingreso_sector_publico' => $this->fecha_ingreso_sector_publico,
            'fecha_nombramiento'           => $this->fecha_nombramiento,
            'puede_marcar'                 => $this->puede_marcar,
            // Derivado de la fecha de ingreso, no una columna.
            'anios_servicio'               => $this->anios_servicio,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,

            // ── Relaciones, solo si fueron cargadas ─────────────────
            'unidad_administrativa' => $this->whenLoaded('unidadAdministrativa'),
            // `rmu` es un accesor del puesto —sale de su grupo ocupacional— y
            // por eso no viaja en toArray(). Se agrega explícitamente porque
            // de él depende la remuneración heredada de la escala LOSEP.
            'puesto' => $this->whenLoaded('puesto', fn () => [
                ...$this->puesto->toArray(),
                'rmu' => $this->puesto->rmu,
            ]),
            'contrato_vigente' => $this->whenLoaded('contratoVigente'),
            // Derivado, no una columna: true si el servidor no tiene ningún
            // ContratoServidor vigente. No confundir con Servidor.estado
            // (activo/inactivo) — son conceptos independientes. Cálculo
            // directo (no whenLoaded con closure) para no depender de su
            // resolución interna.
            'pendiente_vinculacion' => $this->resource->relationLoaded('contratoVigente')
                ? is_null($this->resource->contratoVigente)
                : null,
            'user'        => $this->whenLoaded('usuario'),
            'documentos'  => DocumentoServidorResource::collection($this->whenLoaded('documentos')),
            'movimientos' => $this->whenLoaded('movimientos'),
        ];
    }
}
