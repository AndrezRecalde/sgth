<?php

namespace App\Http\Resources\InventarioTi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Las reglas del préstamo (D16).
 *
 * @mixin \App\Models\InventarioTi\ReglasPrestamo
 */
class ReglasPrestamoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'plazo_maximo_dias_habiles'   => $this->plazo_maximo_dias_habiles,
            'anticipacion_minima_minutos' => $this->anticipacion_minima_minutos,
            'hora_apertura'               => $this->apertura(),
            'hora_cierre'                 => $this->cierre(),
            'presta_fines_de_semana'      => $this->presta_fines_de_semana,
            'bloquea_con_vencido'         => $this->bloquea_con_vencido,
            'margen_retiro_minutos'       => $this->margen_retiro_minutos,
            'actualizado_por'             => $this->whenLoaded('actualizadoPor', fn () => $this->actualizadoPor?->nombre_completo),
            'updated_at'                  => $this->updated_at,
        ];
    }
}
