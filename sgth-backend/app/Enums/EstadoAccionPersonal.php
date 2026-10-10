<?php

namespace App\Enums;

/**
 * Estados de una Acción de Personal: borrador → suscrita → registrada →
 * notificada, más anulada, a la que se llega desde cualquiera de ellos.
 *
 * Los estados intermedios 'informe_uath' y 'dictamen_presupuestario' se
 * retiraron el 2026-07-29: no capturaban ningún dato y el flujo real de
 * Talento Humano no los usa. La verificación presupuestaria sigue existiendo
 * como guarda al suscribir (ver MovimientoPersonalStateService), no como
 * estado.
 */
enum EstadoAccionPersonal: string
{
    case BORRADOR   = 'borrador';
    case SUSCRITA   = 'suscrita';
    case REGISTRADA = 'registrada';
    case NOTIFICADA = 'notificada';
    case ANULADA    = 'anulada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BORRADOR   => 'Borrador',
            self::SUSCRITA   => 'Suscrita',
            self::REGISTRADA => 'Registrada',
            self::NOTIFICADA => 'Notificada',
            self::ANULADA    => 'Anulada',
        };
    }

    /**
     * El permiso que hace falta para llevar una acción a este estado. Al
     * borrador no se llega: se nace en él, y crearlo o editarlo pide
     * PREPARAR_ACCION_PERSONAL.
     */
    public function permisoParaLlegar(): ?Permiso
    {
        return match ($this) {
            self::BORRADOR   => null,
            self::SUSCRITA   => Permiso::SUSCRIBIR_ACCION_PERSONAL,
            self::REGISTRADA => Permiso::REGISTRAR_ACCION_PERSONAL,
            self::NOTIFICADA => Permiso::NOTIFICAR_ACCION_PERSONAL,
            self::ANULADA    => Permiso::ANULAR_ACCION_PERSONAL,
        };
    }

    /**
     * Lo que el titular ve de sus propias acciones: lo que ya es un acto. Ni el
     * borrador ni lo que se suscribió y aún no se registra —el expediente le
     * mostraba la cesación o la sanción que Talento Humano estaba preparando—,
     * ni lo que se anuló sin llegar a tener número.
     *
     * @return list<self>
     */
    public static function visiblesParaElTitular(): array
    {
        return [self::REGISTRADA, self::NOTIFICADA, self::ANULADA];
    }
}
