<?php

namespace App\Http\Resources\Comunicacion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Una versión de una variante del logo. El archivo se pide aparte, con sesión,
 * por su id (`comunicacion/identidad/versiones/{id}/archivo`): una programada
 * todavía no es pública.
 *
 * @mixin \App\Models\Comunicacion\RecursoIdentidad
 */
class RecursoIdentidadResource extends JsonResource
{
    /** El id de la versión que rige hoy en su variante, para decir su estado. */
    private ?int $vigenteId = null;

    public function conVigente(?int $vigenteId): static
    {
        $this->vigenteId = $vigenteId;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'variante'          => $this->variante->value,
            'variante_etiqueta' => $this->variante->etiqueta(),
            'mime'              => $this->mime,
            'ancho'             => $this->ancho,
            'alto'              => $this->alto,
            'peso'              => $this->peso,
            'vigente_desde'     => $this->vigente_desde->toDateString(),
            // vigente, programada, anterior o anulada.
            'estado'            => match (true) {
                $this->anulada_en !== null                        => 'anulada',
                $this->vigente_desde->isAfter(Carbon::today())    => 'programada',
                $this->vigenteId === $this->id                    => 'vigente',
                default                                           => 'anterior',
            },
            'origen'            => $this->origen->value,
            'origen_etiqueta'   => $this->origen->etiqueta(),
            'reutiliza_id'      => $this->reutiliza_id,
            'nota'              => $this->nota,
            'subido_en'         => $this->created_at?->toIso8601String(),
            'subido_por'        => $this->whenLoaded('subidoPor', fn () => $this->subidoPor ? [
                'id'     => $this->subidoPor->id,
                'nombre' => $this->subidoPor->nombre_completo,
            ] : null),
            'anulada_en'        => $this->anulada_en?->toIso8601String(),
            'anulada_por'       => $this->whenLoaded('anuladaPor', fn () => $this->anuladaPor ? [
                'id'     => $this->anuladaPor->id,
                'nombre' => $this->anuladaPor->nombre_completo,
            ] : null),
        ];
    }
}
