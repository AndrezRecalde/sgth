<?php

namespace App\Http\Resources\InventarioTi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un contrato de vigencia tecnológica, con su cronograma y cuántos equipos
 * cubre.
 *
 * @mixin \App\Models\InventarioTi\ContratoVigencia
 */
class ContratoVigenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hoy = now()->startOfDay();

        return [
            'id'               => $this->id,
            'proceso'          => $this->proceso,
            'proveedor'        => $this->proveedor,
            'objeto'           => $this->objeto,
            'administrador_id' => $this->administrador_id,
            'fecha_inicio'     => $this->fecha_inicio?->toDateString(),
            'fecha_fin'        => $this->fecha_fin?->toDateString(),
            // Vigente mientras no haya pasado su fin; sin fin, se da por vigente.
            'vigente'          => $this->fecha_fin === null || $this->fecha_fin->greaterThanOrEqualTo($hoy),
            'observaciones'    => $this->observaciones,
            'bienes_count'     => $this->bienes_count ?? null,
            'administrador'    => $this->whenLoaded('administrador', fn () => $this->administrador ? [
                'id'     => $this->administrador->id,
                'cedula' => $this->administrador->cedula,
                'nombre' => trim("{$this->administrador->apellido} {$this->administrador->nombre}"),
            ] : null),
            'cronograma' => $this->whenLoaded('cronograma', fn () => $this->cronograma->map(fn ($c) => [
                'id'          => $c->id,
                'fecha_desde' => $c->fecha_desde?->toDateString(),
                'fecha_hasta' => $c->fecha_hasta?->toDateString(),
                'descripcion' => $c->descripcion,
            ])->all()),
        ];
    }
}
