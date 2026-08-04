<?php

namespace App\Http\Resources\Expediente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovimientoPersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $datos = parent::toArray($request);

        // parent::toArray() snake-casea las relaciones cargadas
        // (Str::snake('autorizadoPor') === 'autorizado_por'), lo que
        // pisa el FK crudo con el modelo User completo -- incluido el
        // servidor anidado con datos personales. Se restaura el FK y se
        // expone el nombre por separado, explícitamente.
        $datos['autorizado_por'] = $this->getRawOriginal('autorizado_por');

        $datos['partida_origen'] = $this->whenLoaded('partidaOrigen');
        $datos['unidad_origen']  = $this->whenLoaded('unidadOrigen');
        $datos['unidad_destino'] = $this->whenLoaded('unidadDestino');
        $datos['puesto_origen']  = $this->whenLoaded('puestoOrigen');
        $datos['puesto_destino'] = $this->whenLoaded('puestoDestino');
        // La ausencia que este ingreso cubre, con el titular ausente: es lo que
        // hace legible "reemplaza a X" sin una consulta extra desde la UI.
        $datos['cubre_movimiento'] = $this->whenLoaded('cubreMovimiento');

        $datos['autorizado_por_usuario'] = $this->whenLoaded(
            'autorizadoPor',
            fn () => [
                'id' => $this->autorizadoPor->id,
                'nombre_completo' => $this->autorizadoPor->nombre_completo,
            ]
        );

        return $datos;
    }
}
