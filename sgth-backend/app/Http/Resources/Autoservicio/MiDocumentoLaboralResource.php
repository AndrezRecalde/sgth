<?php

namespace App\Http\Resources\Autoservicio;

use App\Enums\EstadoAccionPersonal;
use App\Services\Autoservicio\MisDocumentosLaboralesService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un documento laboral en el portal del servidor (fase 6.3a): qué acto es,
 * desde cuándo rige, cuándo se le notificó y si acusó recibo.
 *
 * @mixin \App\Models\Expediente\MovimientoPersonal
 */
class MiDocumentoLaboralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'codigo_registro'    => $this->codigo_registro,
            'accion'             => $this->etiqueta(),
            'causal'             => $this->causal()?->etiqueta(),
            'instrumento'        => $this->instrumento,
            'instrumento_etiqueta' => $this->instrumento?->etiqueta(),
            'fecha_efectiva'     => $this->fecha_efectiva?->toDateString(),
            'anulada'            => $this->estado === EstadoAccionPersonal::ANULADA,
            'motivo_anulacion'   => $this->estado === EstadoAccionPersonal::ANULADA ? $this->motivo_anulacion : null,
            'fecha_notificacion' => $this->fecha_notificacion,
            'notificacion_medio' => $this->notificacion_medio,
            'acuse_en'           => $this->acuse_en?->toIso8601String(),
            'puede_acusar'       => app(MisDocumentosLaboralesService::class)->puedeAcusar($this->resource),
        ];
    }
}
