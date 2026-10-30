<?php

namespace App\Http\Resources\Expediente;

use App\Models\Expediente\Servidor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El servidor que devuelve el buscador de los formularios
 * (docs/plan-roles-y-accesos.md, PR 2).
 *
 * Lo pide quien tiene que elegir a una persona —TIC para un ticket o una
 * custodia, el auditor para un filtro—, que no tiene por qué ver su
 * expediente. Por eso no es ServidorResource: nada de contacto, salud ni
 * documentos.
 *
 * Lo del vínculo —nombramiento, remuneración, el RMU del puesto— solo va a
 * quien puede ver el Expediente (`ServidorPolicy::verAny`, Talento Humano), que
 * lo necesita en Acciones de Personal, Subrogaciones y el sumario. Al resto no
 * le llega ni la clave.
 *
 * @mixin \App\Models\Expediente\Servidor
 */
class ServidorBusquedaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $veVinculo = (bool) $request->user()?->can('verAny', Servidor::class);

        return [
            'id'               => $this->id,
            'cedula'           => $this->cedula,
            'nombre'           => $this->nombre,
            'segundo_nombre'   => $this->segundo_nombre,
            'apellido'         => $this->apellido,
            'segundo_apellido' => $this->segundo_apellido,
            'regimen_laboral'  => $this->regimen_laboral,
            // Estado propio del servidor (activo/inactivo). Marcaciones enseña
            // el historial de quien ya no está.
            'estado'           => $this->estado,
            'puede_marcar'     => $this->puede_marcar,
            // Permisos y vacaciones ofrecen como firmante solo a los jefes.
            'es_jefe'          => (bool) $this->puesto?->es_jefe,

            'unidad_administrativa' => $this->whenLoaded('unidadAdministrativa', fn () => $this->unidadAdministrativa ? [
                'id'     => $this->unidadAdministrativa->id,
                'nombre' => $this->unidadAdministrativa->nombre,
            ] : null),

            // ── Solo para Talento Humano ────────────────────────────
            'contrato_vigente' => $this->when($veVinculo, fn () => $this->contratoVigente ? [
                'tipo_nombramiento' => $this->contratoVigente->tipo_nombramiento,
                'remuneracion'      => $this->contratoVigente->remuneracion,
            ] : null),
            // `rmu` es un accesor: sale del grupo ocupacional del puesto.
            'puesto' => $this->when($veVinculo, fn () => $this->puesto ? [
                'rmu' => $this->puesto->rmu,
            ] : null),
            // Sin contrato vigente: el formulario de acciones ofrece el ingreso.
            'pendiente_vinculacion' => $this->when($veVinculo, fn () => is_null($this->contratoVigente)),
            // El de su último vínculo, vigente o no: decide si cabe la
            // restitución o el reintegro (fase 4.5b).
            'ultimo_nombramiento' => $this->when(
                $veVinculo,
                fn () => $this->ultimoContrato?->tipo_nombramiento?->value,
            ),
        ];
    }
}
