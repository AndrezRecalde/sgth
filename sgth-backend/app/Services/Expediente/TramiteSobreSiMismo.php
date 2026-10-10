<?php

namespace App\Services\Expediente;

use App\Exceptions\ReglaNegocioException;

/**
 * Nadie tramita actos sobre sí mismo (TH 24; diseño de Acciones de Personal,
 * 6.3): ni prepara, ni edita, ni hace avanzar una acción de personal de la que
 * es el titular. Hasta la fase 1.3 nada lo impedía, y quien trabaja en Talento
 * Humano podía suscribirse su propio ingreso o anularse su propia sanción.
 *
 * Va en los servicios y no en una policy: el `Gate::before` de admin-ti
 * contesta antes que cualquier policy, y una regla de coherencia no debe tener
 * atajo. Sin usuario autenticado —un comando, una tarea programada— no hay
 * nadie que se esté tramitando nada, y no aplica.
 */
final class TramiteSobreSiMismo
{
    public static function impedir(int $servidorId): void
    {
        $propio = auth()->user()?->servidor_id;

        if ($propio !== null && (int) $propio === $servidorId) {
            throw new ReglaNegocioException(
                'No puede tramitar una acción de personal sobre usted mismo: '
                    .'tiene que hacerlo otra persona de Talento Humano.'
            );
        }
    }
}
