<?php

namespace App\Http\Resources\Autoservicio;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una orden de exámenes como la ve el propio servidor en el Portal (fase 6 de
 * docs/diseno-examenes-medicos.md, 5.7): qué se le pidió, dónde y cómo va cada
 * examen.
 *
 * Campo por campo: ni el diagnóstico ni lo que el médico leyó en los
 * resultados (D9), ni el motivo de una anulación.
 *
 * @mixin \App\Models\Dispensario\OrdenExamen
 */
class MiOrdenExamenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $servidor = $this->solicitante?->servidor;

        return [
            'id'                  => $this->id,
            'folio'               => $this->folio,
            'fecha_solicitud'     => $this->fecha_solicitud?->toDateString(),
            'estado'              => $this->estado,
            'vencida'             => (bool) $this->vencida,
            'prioridad'           => $this->prioridad,
            'laboratorio_destino' => $this->laboratorio_destino,
            'indicaciones'        => $this->indicaciones,
            /** Si la pidió la evaluación ocupacional y no una consulta. */
            'ocupacional'         => $this->ficha_salud_ocupacional_id !== null,
            'solicitante'         => $servidor
                ? trim("{$servidor->nombre} {$servidor->apellido}")
                : $this->solicitante?->usuario_ti,
            'items'               => MiExamenDeOrdenResource::collection($this->whenLoaded('items')),
            'envios'              => MiEnvioResultadoResource::collection($this->whenLoaded('envios')),
        ];
    }
}
