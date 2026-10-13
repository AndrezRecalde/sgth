<?php

namespace App\Http\Resources\InventarioTi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un mantenimiento: qué se hizo, quién, cuándo y con qué contrato. Si se
 * anuló, quién y por qué.
 *
 * @mixin \App\Models\InventarioTi\MantenimientoBien
 */
class MantenimientoBienResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'bien_informatico_id'  => $this->bien_informatico_id,
            'ticket_id'            => $this->ticket_id,
            'tipo_mantenimiento'   => $this->tipo_mantenimiento?->value,
            'tipo_etiqueta'        => $this->tipo_mantenimiento?->etiqueta(),
            'realizado_por'        => $this->realizado_por,
            'fecha_mantenimiento'  => $this->fecha_mantenimiento?->toDateString(),
            'descripcion'          => $this->descripcion,
            'costo'                => (float) $this->costo,
            'soporte_anterior'     => $this->soporte_anterior,
            'anulado'              => $this->anulado_en !== null,
            'anulado_en'           => $this->anulado_en,
            'motivo_anulacion'     => $this->motivo_anulacion,
            'tecnico'              => $this->whenLoaded('tecnico', fn () => $this->tecnico ? [
                'id' => $this->tecnico->id, 'nombre' => $this->tecnico->nombre_completo,
            ] : null),
            'contrato_vigencia'    => $this->whenLoaded('contratoVigencia', fn () => $this->contratoVigencia ? [
                'id' => $this->contratoVigencia->id, 'proceso' => $this->contratoVigencia->proceso,
                'proveedor' => $this->contratoVigencia->proveedor,
            ] : null),
            'registrado_por'       => $this->whenLoaded('registradoPor', fn () => $this->registradoPor?->nombre_completo),
            'anulado_por'          => $this->whenLoaded('anuladoPor', fn () => $this->anuladoPor?->nombre_completo),
            'bien'                 => $this->whenLoaded('bien', fn () => $this->bien ? [
                'id' => $this->bien->id, 'codigo_institucional' => $this->bien->codigo_institucional,
                'tipo' => $this->bien->tipo?->nombre, 'marca' => $this->bien->marca?->nombre, 'modelo' => $this->bien->modelo,
            ] : null),
        ];
    }
}
