<?php

namespace App\Http\Resources\InventarioTi;

use App\Enums\EstadoPrestamo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un préstamo de equipos: quién, para qué, las horas previstas y reales, los
 * equipos con su condición y el trámite.
 *
 * @mixin \App\Models\InventarioTi\PrestamoEquipo
 */
class PrestamoEquipoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $usuario = fn (string $relacion) => $this->whenLoaded($relacion, fn () => $this->{$relacion}?->nombre_completo);

        return [
            'id'                 => $this->id,
            'folio'              => $this->folio,
            'estado'             => $this->estado->value,
            'estado_etiqueta'    => $this->estado->etiqueta(),
            'vencido'            => $this->vencido(),
            'cancelable'         => in_array($this->estado, [EstadoPrestamo::SOLICITADO, EstadoPrestamo::RESERVADO], true),
            'directo'            => $this->directo,
            'servidor_id'        => $this->servidor_id,
            'servidor'           => $this->whenLoaded('servidor', fn () => $this->servidor ? [
                'id' => $this->servidor->id, 'cedula' => $this->servidor->cedula,
                'nombre' => trim("{$this->servidor->apellido} {$this->servidor->nombre}"),
            ] : null),
            'unidad'             => $this->whenLoaded('unidadAdministrativa', fn () => $this->unidadAdministrativa ? [
                'id' => $this->unidadAdministrativa->id, 'nombre' => $this->unidadAdministrativa->nombre,
                'acronimo' => $this->unidadAdministrativa->acronimo,
            ] : null),
            'motivo'             => $this->motivo,
            'lugar_uso'          => $this->lugar_uso,
            'fuera_del_edificio' => $this->fuera_del_edificio,
            'inicio_previsto'    => $this->inicio_previsto,
            'fin_previsto'       => $this->fin_previsto,
            'aprobado_en'        => $this->aprobado_en,
            'rechazado_en'       => $this->rechazado_en,
            'motivo_rechazo'     => $this->motivo_rechazo,
            'cancelado_en'       => $this->cancelado_en,
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'entregado_en'       => $this->entregado_en,
            'devuelto_en'        => $this->devuelto_en,
            'observaciones'      => $this->observaciones,
            'registrado_por'     => $usuario('registradoPor'),
            'aprobado_por'       => $usuario('aprobadoPor'),
            'rechazado_por'      => $usuario('rechazadoPor'),
            'cancelado_por'      => $usuario('canceladoPor'),
            'entregado_por'      => $usuario('entregadoPor'),
            'recibido_por'       => $usuario('recibidoPor'),
            'items'              => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id'                   => $i->id,
                'bien'                 => $i->bien ? [
                    'id' => $i->bien->id, 'codigo_institucional' => $i->bien->codigo_institucional,
                    'tipo' => $i->bien->tipo?->nombre, 'marca' => $i->bien->marca?->nombre, 'modelo' => $i->bien->modelo,
                ] : null,
                'accesorios'           => $i->accesorios,
                'condicion_entrega'    => $i->condicion_entrega?->value,
                'condicion_devolucion' => $i->condicion_devolucion?->value,
                'novedad'              => $i->novedad,
            ])->values()),
            'historial'          => $this->whenLoaded('historial', fn () => $this->historial->map(fn ($h) => [
                'estado_anterior' => $h->estado_anterior?->value,
                'estado_nuevo'    => $h->estado_nuevo->value,
                'etiqueta'        => $h->estado_nuevo->etiqueta(),
                'usuario'         => $h->usuario?->nombre_completo,
                'detalle'         => $h->detalle,
                'fecha'           => $h->created_at,
            ])->values()),
        ];
    }
}
