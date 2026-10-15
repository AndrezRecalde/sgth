<?php

namespace App\Http\Resources\Comunicacion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una credencial con su historia: el estado, la revocación, la devolución de
 * la tarjeta y sus impresiones (docs/diseno-comunicacion-social.md, 6.3).
 *
 * @mixin \App\Models\Comunicacion\Credencial
 */
class CredencialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'servidor_id'        => $this->servidor_id,
            'estado'             => $this->estado->value,
            'estado_etiqueta'    => $this->estado->etiqueta(),
            'vigente'            => $this->estaVigente(),
            'valida_hasta'       => $this->valida_hasta?->toDateString(),
            'url_verificacion'   => $this->urlVerificacion(),
            'creada_en'          => $this->created_at?->toIso8601String(),
            'reemplaza_a_id'     => $this->reemplaza_a_id,
            'revocada_en'        => $this->revocada_en?->toIso8601String(),
            'revocada_por'       => $this->whenLoaded('revocadaPor', fn () => $this->revocadaPor ? [
                'id'     => $this->revocadaPor->id,
                'nombre' => $this->revocadaPor->nombre_completo,
            ] : null),
            'motivo_revocacion'  => $this->motivo_revocacion?->value,
            'motivo_etiqueta'    => $this->motivo_revocacion?->etiqueta(),
            'detalle_revocacion' => $this->detalle_revocacion,
            'anulada_en'         => $this->anulada_en?->toIso8601String(),
            'devuelta_en'        => $this->devuelta_en?->toDateString(),
            'devolucion_registrada_por' => $this->whenLoaded('devolucionRegistradaPor', fn () => $this->devolucionRegistradaPor ? [
                'id'     => $this->devolucionRegistradaPor->id,
                'nombre' => $this->devolucionRegistradaPor->nombre_completo,
            ] : null),
            'devolucion_nota'    => $this->devolucion_nota,
            'impresiones'        => ImpresionCredencialResource::collection($this->whenLoaded('impresiones')),
        ];
    }
}
