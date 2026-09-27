<?php

namespace App\Http\Resources\Expediente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una línea de la bitácora de certificados laborales.
 *
 * Deliberadamente NO lleva `datos`: ahí está congelada la foto del expediente
 * —incluida la remuneración de cada período— y esto es un registro de quién
 * pidió qué y cuándo, no una segunda copia del documento. Quien necesite el
 * contenido, que emita uno nuevo o verifique el código.
 *
 * @mixin \App\Models\Expediente\EmisionCertificadoLaboral
 */
class EmisionCertificadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'codigo'           => $this->codigo,
            'tipo'             => $this->tipo->value,
            'tipo_titulo'      => $this->tipo->titulo(),
            'con_remuneracion' => $this->con_remuneracion,
            'emitido_en'       => $this->emitido_en?->format('Y-m-d H:i'),
            'vence_en'         => $this->vence_en?->format('Y-m-d'),
            'vigente'          => $this->estaVigente(),
            'emitido_por'      => $this->whenLoaded(
                'emitidoPor',
                fn () => $this->emitidoPor?->nombre_completo,
            ),
            'firmante_nombre'  => $this->firmante_nombre,
        ];
    }
}
