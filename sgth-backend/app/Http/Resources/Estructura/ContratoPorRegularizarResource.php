<?php

namespace App\Http\Resources\Estructura;

use App\Models\Estructura\Puesto;
use App\Support\NombreDelServidor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un ocasional o un profesional en un puesto de plantilla, sin reemplazar a
 * nadie, con los puestos de contrato a los que pasa sin que cambie su
 * situación (P9, PR 4).
 *
 * @mixin \App\Models\Expediente\ContratoServidor
 */
class ContratoPorRegularizarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'servidor_id'       => $this->servidor_id,
            'servidor'          => $this->servidor ? NombreDelServidor::de($this->servidor) : null,
            'tipo_nombramiento' => $this->tipo_nombramiento,
            'modalidad'         => $this->tipo_nombramiento?->etiqueta(),
            'puesto'            => $this->puesto ? [
                'id'     => $this->puesto->id,
                'cargo'  => $this->puesto->cargo?->nombre,
                'unidad' => $this->puesto->unidadAdministrativa?->nombre,
            ] : null,
            /**
             * Los de su misma unidad, con su régimen y, si es ocasional, su
             * grupo ocupacional. Vacío: hay que crear el puesto de contrato.
             *
             * @var list<array{id: int, cargo: string|null}>
             */
            'destinos' => collect($this->resource->relationLoaded('destinosDeContrato')
                ? $this->resource->getRelation('destinosDeContrato')
                : [])
                ->map(fn (Puesto $p) => ['id' => $p->id, 'cargo' => $p->cargo?->nombre])
                ->values()
                ->all(),
        ];
    }
}
