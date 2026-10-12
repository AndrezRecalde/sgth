<?php

namespace App\Http\Resources\Expediente;

use App\Enums\EstadoSolicitudAccion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una solicitud del portal (fase 6.3b): qué pidió, desde cuándo, en qué quedó
 * y con qué acción se atendió. El respaldo no sale: se descarga por su
 * endpoint. Lo ven el servidor —las suyas— y Talento Humano.
 *
 * @mixin \App\Models\Expediente\SolicitudAccionPersonal
 */
class SolicitudAccionPersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $servidor = $this->servidor;
        // Al titular, la acción solo desde que la puede ver: un borrador no es
        // suyo de ver (diseño, 6.3), aunque atienda lo que pidió.
        $accion = $this->movimientoPersonal;
        if ($accion && $accion->esSobre($request->user()) && ! $accion->esVisibleParaElTitular()) {
            $accion = null;
        }

        return [
            'id'                   => $this->id,
            'folio'                => $this->folio,
            'tipo'                 => $this->tipo,
            'tipo_etiqueta'        => $this->tipo->etiqueta(),
            'causal'               => $this->causal,
            'causal_etiqueta'      => $this->causal?->etiqueta(),
            'servidor'             => $servidor ? [
                'id'       => $servidor->id,
                'nombre'   => $servidor->nombre,
                'apellido' => $servidor->apellido,
                'cedula'   => $servidor->cedula,
            ] : null,
            'fecha_presentacion'   => $this->fecha_presentacion?->toDateString(),
            'fecha_desde'          => $this->fecha_desde?->toDateString(),
            'fecha_hasta'          => $this->fecha_hasta?->toDateString(),
            'motivo'               => $this->motivo,
            'documento_nombre'     => $this->documento_nombre,
            'estado'               => $this->estado,
            'estado_etiqueta'      => $this->estado->etiqueta(),
            // La renuncia sin respuesta a los 15 días (Reglamento a la LOSEP, Art. 102).
            'aceptada_por_el_plazo' => $this->aceptadaPorElPlazo(),
            'motivo_rechazo'       => $this->motivo_rechazo,
            'atendida_en'          => $this->atendida_en?->toIso8601String(),
            'retirada_en'          => $this->retirada_en?->toIso8601String(),
            'accion'               => $accion ? [
                'id'              => $accion->id,
                'codigo_registro' => $accion->codigo_registro,
                'estado'          => $accion->estado,
            ] : null,
            'puede_retirar'        => $this->estado === EstadoSolicitudAccion::PENDIENTE
                && $request->user()?->servidor_id === $this->servidor_id,
        ];
    }
}
