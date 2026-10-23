<?php

namespace App\Http\Resources\Autoservicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un resultado que el servidor envió desde el Portal, como lo ve él: qué
 * envió y en qué quedó. Si lo rechazaron, por qué; no quién.
 *
 * @mixin \App\Models\Dispensario\EnvioResultado
 */
class MiEnvioResultadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            /** @var list<int> Los exámenes de la orden que dijo que cubre. */
            'items'           => array_map('intval', $this->items ?? []),
            'fecha_resultado' => $this->fecha_resultado?->toDateString(),
            'comentario'      => $this->comentario,
            'estado'          => $this->estado,
            'motivo_rechazo'  => $this->motivo_rechazo,
            'enviado_en'      => $this->created_at?->toIso8601String(),
            'atendido_en'     => $this->atendido_en?->toIso8601String(),
        ];
    }
}
