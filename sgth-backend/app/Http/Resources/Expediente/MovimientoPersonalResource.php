<?php

namespace App\Http\Resources\Expediente;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El arreglo va enumerado campo por campo, no con `parent::toArray()`.
 *
 * Scramble solo infiere la forma de un recurso cuando `toArray()` devuelve un
 * arreglo literal; ante `parent::toArray()` emite `unknown[]` y el tipo llega
 * inservible al frontend. Si agregas una columna a `movimientos_personal` o
 * cargas una relación nueva en el controlador, agrégala también aquí: al
 * enumerar ya no hay volcado automático que las arrastre.
 */
class MovimientoPersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'servidor_id'        => $this->servidor_id,
            'tipo_movimiento'    => $this->tipo_movimiento,
            'subtipo_movimiento' => $this->subtipo_movimiento,
            'categoria'          => $this->categoria,
            'estado'             => $this->estado,
            'descripcion'        => $this->descripcion,
            'observacion'        => $this->observacion,

            'fecha_efectiva' => $this->fecha_efectiva,
            'fecha_inicio'   => $this->fecha_inicio,
            'fecha_fin'      => $this->fecha_fin,

            'unidad_origen_id'  => $this->unidad_origen_id,
            'unidad_destino_id' => $this->unidad_destino_id,
            'puesto_origen_id'  => $this->puesto_origen_id,
            'puesto_destino_id' => $this->puesto_destino_id,

            // Datos del vínculo que Talento Humano fija en borrador y que se
            // materializan en el ContratoServidor al registrar la acción.
            'tipo_nombramiento_propuesto' => $this->tipo_nombramiento_propuesto,
            'remuneracion_propuesta'      => $this->remuneracion_propuesta,
            'numero_contrato'             => $this->numero_contrato,
            'partida_presupuestaria_id'   => $this->partida_presupuestaria_id,
            'puede_marcar'                => $this->puede_marcar,
            'requiere_dictamen_medico'    => $this->requiere_dictamen_medico,
            'fecha_fin_propuesta'         => $this->fecha_fin_propuesta,

            // Situación actual congelada al crear la acción: no se deriva del
            // puesto de origen, que pudo cambiar de escala o de partida desde
            // entonces.
            'remuneracion_origen' => $this->remuneracion_origen,
            'partida_origen_id'   => $this->partida_origen_id,

            'movimiento_previo_id' => $this->movimiento_previo_id,
            'corrige_a_id'         => $this->corrige_a_id,
            /** Ausencia temporal que este ingreso viene a cubrir. */
            'cubre_movimiento_id'  => $this->cubre_movimiento_id,

            'codigo'                      => $this->codigo,
            'codigo_registro'             => $this->codigo_registro,
            'fecha_registro'              => $this->fecha_registro,
            'resolucion_numero'           => $this->resolucion_numero,
            'documento_respaldo'          => $this->documento_respaldo,
            'dictamen_presupuestario_ref' => $this->dictamen_presupuestario_ref,

            // Firmantes sellados al suscribir: se copian dentro de la acción
            // para que una reimpresión no atribuya la firma a quien ocupe hoy
            // el cargo.
            'fecha_suscripcion'         => $this->fecha_suscripcion,
            'firmante_autoridad_id'     => $this->firmante_autoridad_id,
            'firmante_autoridad_nombre' => $this->firmante_autoridad_nombre,
            'firmante_autoridad_cargo'  => $this->firmante_autoridad_cargo,
            'firmante_autoridad_cedula' => $this->firmante_autoridad_cedula,
            'firmante_th_id'            => $this->firmante_th_id,
            'firmante_th_nombre'        => $this->firmante_th_nombre,
            'firmante_th_cargo'         => $this->firmante_th_cargo,
            'firmante_th_cedula'        => $this->firmante_th_cedula,

            'lugar_trabajo'  => $this->lugar_trabajo,
            'caucionado'     => $this->caucionado,
            'caucion_numero' => $this->caucion_numero,
            'caucion_fecha'  => $this->caucion_fecha,

            // Al enumerar, la relación cargada ya no pisa el FK. Antes sí:
            // `parent::toArray()` snake-caseaba `autorizadoPor` a
            // `autorizado_por` y sustituía el id por el User completo, con el
            // servidor anidado y sus datos personales.
            'autorizado_por'     => $this->autorizado_por,
            'notificado_por'     => $this->notificado_por,
            'fecha_notificacion' => $this->fecha_notificacion,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // ── Relaciones, solo si fueron cargadas ─────────────────
            'servidor'       => $this->whenLoaded('servidor'),
            'unidad_origen'  => $this->whenLoaded('unidadOrigen'),
            'unidad_destino' => $this->whenLoaded('unidadDestino'),
            'puesto_origen'  => $this->whenLoaded('puestoOrigen'),
            // `rmu` es un accesor del puesto —sale de su grupo ocupacional— y
            // no viaja en toArray(). El cierre del vínculo lo necesita para
            // sugerir la remuneración de la escala en los ingresos LOSEP.
            'puesto_destino' => $this->whenLoaded('puestoDestino', fn () => [
                ...$this->puestoDestino->toArray(),
                'rmu' => $this->puestoDestino->rmu,
            ]),
            'partida_origen'          => $this->whenLoaded('partidaOrigen'),
            'partida_presupuestaria'  => $this->whenLoaded('partidaPresupuestaria'),
            'solicitud_certificacion' => $this->whenLoaded('solicitudCertificacion'),
            'movimiento_previo'       => $this->whenLoaded('movimientoPrevio'),
            // La ausencia que este ingreso cubre, con el titular ausente: es
            // lo que hace legible "reemplaza a X" sin una consulta extra.
            'cubre_movimiento'        => $this->whenLoaded('cubreMovimiento'),

            'autorizado_por_usuario' => $this->whenLoaded(
                'autorizadoPor',
                fn () => [
                    'id'              => $this->autorizadoPor->id,
                    'nombre_completo' => $this->autorizadoPor->nombre_completo,
                ]
            ),
        ];
    }
}
