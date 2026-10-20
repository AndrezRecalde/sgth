<?php

namespace App\Services\Asistencia;

use App\Helpers\DiasHabilesHelper;
use Carbon\Carbon;

/**
 * Los días que descuentan unas vacaciones tomadas por partes (TH, 2026-10-20;
 * criterio del Ministerio del Trabajo, oficio MDT-DJTE-2017-0878-OF): si la
 * parte termina justo antes de un descanso obligatorio —sábado, domingo o
 * feriado—, esos días se cuentan hasta la víspera del regreso; los que van
 * antes del inicio, no. De martes a viernes son seis días; de lunes a jueves,
 * cuatro; un viernes suelto, tres.
 *
 * Vale para la LOSEP y el Código del Trabajo: el oficio respondía sobre el
 * Código, y la LOSEP no regula el caso. El día suelto que solo debe descontar
 * ese día se pide como permiso con cargo a vacaciones (Reglamento de la LOSEP,
 * Art. 32).
 */
class ConteoDeVacaciones
{
    use DiasHabilesHelper;

    /** El día en que vuelve: el primer laborable después del fin. */
    public function retorno(Carbon $fin): Carbon
    {
        return $this->calcularDiasHabiles($fin, 1);
    }

    /** Lo que se descuenta: los días calendario desde el inicio hasta la víspera del retorno. */
    public function dias(Carbon $inicio, Carbon $fin): int
    {
        if ($fin->lt($inicio)) {
            return 0;
        }

        return (int) $inicio->copy()->startOfDay()->diffInDays($this->retorno($fin)->startOfDay()->subDay()) + 1;
    }
}
