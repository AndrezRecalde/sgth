<?php

namespace App\Http\Resources\Estructura;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnidadAdministrativaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'codigo'          => $this->codigo,
            'nombre'          => $this->nombre,
            'acronimo'        => $this->acronimo,
            'descripcion'     => $this->descripcion,
            'unidad_padre_id' => $this->unidad_padre_id,
            'nivel'           => $this->nivel,
            'estado'          => $this->estado,
            // Anclajes de los firmantes de las Acciones de Personal.
            'es_unidad_talento_humano' => (bool) $this->es_unidad_talento_humano,
            'es_maxima_autoridad'      => (bool) $this->es_maxima_autoridad,
            'tipo_unidad'     => $this->whenLoaded('tipoUnidad', fn() => [
                'id'          => $this->tipoUnidad->id,
                'acronimo'    => $this->tipoUnidad->acronimo,
                'descripcion' => $this->tipoUnidad->descripcion,
            ]),
            'puestos_count'   => $this->whenLoaded('puestos',
                fn() => $this->puestos->count(), 0
            ),
            // Quién ejerce hoy por subrogación o encargo. Sin esto el
            // organigrama muestra al titular de un puesto que otra persona
            // está despachando, que es justo lo que hay que poder ver.
            'subrogaciones_vigentes' => $this->whenLoaded('subrogacionesVigentes',
                fn() => $this->subrogacionesVigentes->map(fn ($s) => [
                    'id'           => $s->id,
                    'tipo'         => $s->tipo?->value,
                    'subrogante'   => trim(implode(' ', array_filter([
                        $s->subrogante?->apellido, $s->subrogante?->nombre,
                    ]))) ?: null,
                    'puesto'       => $s->puestoSubrogado?->cargo?->nombre,
                    'fecha_inicio' => $s->fecha_inicio?->toDateString(),
                    'fecha_fin'    => $s->fecha_fin?->toDateString(),
                ])->values(),
                []
            ),
            'hijos'           => UnidadAdministrativaResource::collection(
                $this->whenLoaded('hijos')
            ),
        ];
    }
}
