<?php

namespace App\Http\Resources\InventarioTi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una baja: el informe técnico, cómo se resolvió y si el equipo ya se entregó
 * a Activos Fijos.
 *
 * @mixin \App\Models\InventarioTi\BajaBien
 */
class BajaBienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'numero'               => $this->numero(),
            'bien_informatico_id'  => $this->bien_informatico_id,
            'estado'               => $this->estado->value,
            'estado_etiqueta'      => $this->estado->etiqueta(),
            'motivo'               => $this->motivo->value,
            'motivo_etiqueta'      => $this->motivo->etiqueta(),
            'diagnostico'          => $this->diagnostico,
            'recomendacion'        => $this->recomendacion,
            'fecha_informe'        => $this->fecha_informe?->toDateString(),
            'soporte_anterior'     => $this->soporte_anterior,
            'fecha_baja'           => $this->fecha_baja?->toDateString(),
            'aprobado_en'          => $this->aprobado_en,
            'memorando_numero'     => $this->memorando_numero,
            'tiene_memorando'      => $this->memorando_archivo !== null,
            'memorando_nombre'     => $this->memorando_nombre,
            'entregado_activos_en' => $this->entregado_activos_en?->toDateString(),
            'rechazado_en'         => $this->rechazado_en,
            'motivo_rechazo'       => $this->motivo_rechazo,
            'baja_principal_id'    => $this->baja_principal_id,
            'informado_por'        => $this->whenLoaded('informadoPor', fn () => $this->informadoPor?->nombre_completo),
            'aprobado_por'         => $this->whenLoaded('aprobadoPor', fn () => $this->aprobadoPor?->nombre_completo),
            'rechazado_por'        => $this->whenLoaded('rechazadoPor', fn () => $this->rechazadoPor?->nombre_completo),
            'bien'                 => $this->whenLoaded('bien', fn () => $this->bien ? [
                'id' => $this->bien->id, 'codigo_institucional' => $this->bien->codigo_institucional,
                'codigo_anterior' => $this->bien->codigo_anterior, 'numero_serie' => $this->bien->numero_serie,
                'tipo' => $this->bien->tipo?->nombre, 'marca' => $this->bien->marca?->nombre, 'modelo' => $this->bien->modelo,
            ] : null),
            'principal'            => $this->whenLoaded('principal', fn () => $this->principal ? [
                'id' => $this->principal->id, 'numero' => $this->principal->numero(),
                'codigo_institucional' => $this->principal->bien?->codigo_institucional,
            ] : null),
            'componentes'          => $this->whenLoaded('componentes', fn () => $this->componentes->map(fn ($c) => [
                'id' => $c->id, 'bien_informatico_id' => $c->bien_informatico_id,
                'codigo_institucional' => $c->bien?->codigo_institucional, 'tipo' => $c->bien?->tipo?->nombre,
            ])->values()),
        ];
    }
}
