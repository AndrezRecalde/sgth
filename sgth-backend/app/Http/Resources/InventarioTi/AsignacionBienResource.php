<?php

namespace App\Http\Resources\InventarioTi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una custodia: quién responde por el equipo, quién lo usa, desde cuándo y
 * con qué memorando. La usan el historial de la ficha, el listado de
 * custodias y «Mis equipos» del Portal.
 *
 * @mixin \App\Models\InventarioTi\AsignacionBien
 */
class AsignacionBienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $persona = fn ($s) => $s ? [
            'id'     => $s->id,
            'cedula' => $s->cedula,
            'nombre' => trim("{$s->apellido} {$s->nombre}"),
        ] : null;

        return [
            'id'                  => $this->id,
            'bien_informatico_id' => $this->bien_informatico_id,
            'servidor_id'         => $this->servidor_id,
            'usuario_servidor_id' => $this->usuario_servidor_id,
            'fecha_asignacion'    => $this->fecha_asignacion?->toDateString(),
            'fecha_devolucion'    => $this->fecha_devolucion?->toDateString(),
            'vigente'             => $this->estaVigente(),
            'motivo'              => $this->motivo,
            'observaciones'       => $this->observaciones,
            'memorando_numero'    => $this->memorando_numero,
            'tiene_memorando_pdf' => $this->memorando_archivo !== null,
            'memorando_nombre'    => $this->memorando_nombre,
            'soporte_anterior'    => $this->soporte_anterior,

            'custodio' => $this->whenLoaded('servidor', fn () => $persona($this->servidor)),
            'unidad'   => $this->whenLoaded('servidor', fn () => $this->servidor?->relationLoaded('unidadAdministrativa') && $this->servidor->unidadAdministrativa ? [
                'id'       => $this->servidor->unidadAdministrativa->id,
                'nombre'   => $this->servidor->unidadAdministrativa->nombre,
                'acronimo' => $this->servidor->unidadAdministrativa->acronimo,
            ] : null),
            'usuario'  => $this->whenLoaded('usuario', fn () => $persona($this->usuario)),
            'custodio_anterior' => $this->whenLoaded('anterior', fn () => $this->anterior?->relationLoaded('servidor') ? $persona($this->anterior->servidor) : null),
            'registrada_por' => $this->whenLoaded('registradaPor', fn () => $this->registradaPor?->nombre_completo),
            'bien' => $this->whenLoaded('bien', fn () => $this->bien ? [
                'id'                   => $this->bien->id,
                'codigo_institucional' => $this->bien->codigo_institucional,
                'tipo'                 => $this->bien->tipo?->nombre,
                'subtipo'              => $this->bien->relationLoaded('subtipo') ? $this->bien->subtipo?->nombre : null,
                'marca'                => $this->bien->marca?->nombre,
                'modelo'               => $this->bien->modelo,
                'ubicacion'            => $this->bien->relationLoaded('ubicacion') ? $this->bien->ubicacion?->nombre : null,
            ] : null),
        ];
    }
}
