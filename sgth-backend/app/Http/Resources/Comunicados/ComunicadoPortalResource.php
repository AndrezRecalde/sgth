<?php

namespace App\Http\Resources\Comunicados;

use App\Enums\VigenciaComunicado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una noticia o un comunicado como lo ve el servidor en el Portal
 * (docs/diseno-noticias-comunicados.md, 5). Lleva su propia lectura —si la
 * abrió, si confirmó— y nada de la gestión: ni a quién va, ni quién la
 * redactó, ni cuántos la leyeron.
 *
 * Espera la relación `lecturas` cargada solo con la de esta persona.
 *
 * @mixin \App\Models\Comunicados\Comunicado
 */
class ComunicadoPortalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $lectura = $this->relationLoaded('lecturas') ? $this->lecturas->first() : null;
        $vencido = $this->vigencia() === VigenciaComunicado::VENCIDO;
        $confirmado = $lectura?->confirmado_at !== null;

        return [
            'id'                    => $this->id,
            'tipo'                  => $this->tipo->value,
            'tipo_etiqueta'         => $this->tipo->etiqueta(),
            'titulo'                => $this->titulo,
            'extracto'              => $this->textoDeTarjeta(),
            'cuerpo'                => $this->when($this->relationLoaded('adjuntos'), $this->cuerpo),
            'referencia'            => $this->referencia,
            'estado'                => $this->estado->value,
            // Vencida, ya no sale arriba: la marca solo vale mientras está vigente.
            'fijado'                => $this->fijado && ! $vencido,
            'vencido'               => $vencido,
            'publicado_desde'       => $this->publicado_desde?->toIso8601String(),
            'publicado_hasta'       => $this->publicado_hasta?->toIso8601String(),
            'requiere_confirmacion' => $this->requiere_confirmacion,
            'leido_at'              => $lectura?->leido_at?->toIso8601String(),
            'confirmado_at'         => $lectura?->confirmado_at?->toIso8601String(),
            'por_confirmar'         => $this->requiere_confirmacion && ! $confirmado && ! $vencido,
            'adjuntos_count'        => $this->whenCounted('adjuntos', fn () => (int) $this->adjuntos_count),
            'adjuntos'              => ComunicadoAdjuntoResource::collection($this->whenLoaded('adjuntos')),
        ];
    }
}
