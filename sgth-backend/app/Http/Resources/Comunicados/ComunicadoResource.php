<?php

namespace App\Http\Resources\Comunicados;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una noticia o un comunicado para Talento Humano
 * (docs/diseno-noticias-comunicados.md, 6).
 *
 * `estado` es lo guardado (borrador, publicado, retirado) y decide qué se
 * puede hacer; `vigencia` es lo que se enseña, con programado y vencido
 * deducidos de las fechas. El texto y los adjuntos van solo en el detalle.
 *
 * @mixin \App\Models\Comunicados\Comunicado
 */
class ComunicadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $vigencia = $this->vigencia();

        return [
            'id'                    => $this->id,
            'tipo'                  => $this->tipo->value,
            'tipo_etiqueta'         => $this->tipo->etiqueta(),
            'titulo'                => $this->titulo,
            'resumen'               => $this->resumen,
            // Lo que muestra la tarjeta: el resumen, o el comienzo del texto.
            'extracto'              => $this->textoDeTarjeta(),
            'cuerpo'                => $this->when($this->esDetalle($request), $this->cuerpo),
            'referencia'            => $this->referencia,
            'estado'                => $this->estado->value,
            'vigencia'              => $vigencia->value,
            'vigencia_etiqueta'     => $vigencia->etiqueta(),
            'audiencia'             => $this->audiencia->value,
            'regimenes'             => $this->regimenes ?? [],
            'unidades'              => $this->whenLoaded('unidadesSeleccionadas', fn () => $this->unidadesSeleccionadas
                ->map(fn ($unidad) => [
                    'id'                   => $unidad->id,
                    'nombre'               => $unidad->nombre,
                    'incluye_dependencias' => (bool) $unidad->pivot->incluye_dependencias,
                ])->values()),
            'publicado_desde'       => $this->publicado_desde?->toIso8601String(),
            'publicado_hasta'       => $this->publicado_hasta?->toIso8601String(),
            'fijado'                => $this->fijado,
            'requiere_confirmacion' => $this->requiere_confirmacion,
            'duplicado_de_id'       => $this->duplicado_de_id,
            'creado_por'            => $this->whenLoaded('creadoPor', fn () => $this->persona($this->creadoPor)),
            'publicado_por'         => $this->whenLoaded('publicadoPor', fn () => $this->persona($this->publicadoPor)),
            'publicado_at'          => $this->publicado_at?->toIso8601String(),
            'retirado_por'          => $this->whenLoaded('retiradoPor', fn () => $this->persona($this->retiradoPor)),
            'retirado_at'           => $this->retirado_at?->toIso8601String(),
            'motivo_retiro'         => $this->motivo_retiro,
            'total_destinatarios'   => $this->totalDestinatarios,
            'leidos'                => $this->whenCounted('leidos_count', fn () => (int) $this->leidos_count),
            'confirmados'           => $this->whenCounted('confirmados_count', fn () => (int) $this->confirmados_count),
            'adjuntos_count'        => $this->whenCounted('adjuntos', fn () => (int) $this->adjuntos_count),
            'adjuntos'              => ComunicadoAdjuntoResource::collection($this->whenLoaded('adjuntos')),
            'creado_en'             => $this->created_at?->toIso8601String(),
            'actualizado_en'        => $this->updated_at?->toIso8601String(),
        ];
    }

    /** El texto, largo, sobra en el listado. */
    private function esDetalle(Request $request): bool
    {
        return ! $request->routeIs('comunicados.index');
    }

    /** @return array{id: int, nombre: string}|null */
    private function persona(?User $usuario): ?array
    {
        return $usuario ? ['id' => $usuario->id, 'nombre' => $usuario->nombre_completo] : null;
    }
}
