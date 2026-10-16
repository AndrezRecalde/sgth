<?php

namespace App\Http\Resources\Helpdesk;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un ticket: quién lo abrió, qué pasa, quién lo atiende y su trámite. Las
 * notas internas solo llegan si quien mira puede leerlas (lo filtra el
 * controlador antes de armar el recurso).
 *
 * @mixin \App\Models\Helpdesk\Ticket
 */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $persona = fn (string $relacion) => $this->whenLoaded($relacion, fn () => $this->{$relacion}?->nombre_completo);

        return [
            'id'                  => $this->id,
            'folio'               => $this->codigo_ticket,
            'estado'              => $this->estado->value,
            'estado_etiqueta'     => $this->estado->etiqueta(),
            'prioridad'           => $this->prioridad->value,
            'prioridad_etiqueta'  => $this->prioridad->etiqueta(),
            'canal'               => $this->canal->value,
            'tipo_ticket'         => $this->tipo_ticket,
            'nivel'               => $this->nivel,
            'asunto'              => $this->asunto,
            'descripcion'         => $this->descripcion,
            'solucion'            => $this->solucion,
            'reabierto_veces'     => $this->reabierto_veces,
            'motivo_anulacion'    => $this->motivo_anulacion,
            'created_at'          => $this->created_at,
            'resuelto_en'         => $this->resuelto_en,
            'fecha_cierre'        => $this->fecha_cierre,
            'anulado_en'          => $this->anulado_en,
            // El SLA (fase 3.2), en horas hábiles.
            'sla'                 => [
                'estado'               => $this->estadoSla(),
                'respuesta_horas'      => $this->sla_respuesta_horas,
                'resolucion_horas'     => $this->sla_resolucion_horas,
                'vence_respuesta_en'   => $this->vence_respuesta_en,
                'primera_respuesta_en' => $this->primera_respuesta_en,
                'vence_en'             => $this->fecha_vencimiento_sla,
                'pausado_desde'        => $this->pausado_desde,
                'vencido_en'           => $this->sla_vencido_en,
            ],
            'solicitante_id'      => $this->solicitante_id,
            'tecnico_id'          => $this->tecnico_id,
            'categoria'           => $this->whenLoaded('categoria', fn () => $this->categoria ? [
                'id' => $this->categoria->id, 'nombre' => $this->categoria->nombre, 'es_hardware' => $this->categoria->es_hardware,
                // El de consumibles genera la solicitud a bodega (5.4c).
                'es_consumibles' => (bool) $this->categoria->es_consumibles,
            ] : null),
            'solicitante'         => $this->whenLoaded('solicitante', fn () => $this->solicitante ? [
                'id' => $this->solicitante->id, 'cedula' => $this->solicitante->cedula,
                'nombre' => trim("{$this->solicitante->apellido} {$this->solicitante->nombre}"),
                'unidad' => $this->solicitante->unidadAdministrativa?->acronimo ?? $this->solicitante->unidadAdministrativa?->nombre,
            ] : null),
            'unidad_tic'          => $this->whenLoaded('unidadTic', fn () => $this->unidadTic ? ['id' => $this->unidadTic->id, 'nombre' => $this->unidadTic->nombre] : null),
            'tecnico'             => $this->whenLoaded('tecnico', fn () => $this->tecnico ? ['id' => $this->tecnico->id, 'nombre' => $this->tecnico->nombre_completo] : null),
            'bien'                => $this->whenLoaded('bien', fn () => $this->bien ? [
                'id' => $this->bien->id, 'codigo_institucional' => $this->bien->codigo_institucional,
                'tipo' => $this->bien->tipo?->nombre, 'marca' => $this->bien->marca?->nombre, 'modelo' => $this->bien->modelo,
            ] : null),
            'registrado_por'      => $persona('registradoPor'),
            'resuelto_por'        => $persona('resueltoPor'),
            'anulado_por'         => $persona('anuladoPor'),
            'comentarios'         => ComentarioTicketResource::collection($this->whenLoaded('comentarios')),
            'historial'           => $this->whenLoaded('historial', fn () => $this->historial->map(fn ($h) => [
                'estado_anterior' => $h->estado_anterior?->value,
                'estado_nuevo'    => $h->estado_nuevo->value,
                'etiqueta'        => $h->estado_nuevo->etiqueta(),
                'usuario'         => $h->usuario?->nombre_completo,
                'detalle'         => $h->detalle,
                'fecha'           => $h->created_at,
            ])->values()),
            'encuesta'            => $this->whenLoaded('encuesta', fn () => $this->encuesta ? [
                'calificacion' => $this->encuesta->calificacion, 'comentarios' => $this->encuesta->comentarios,
            ] : null),
        ];
    }
}
