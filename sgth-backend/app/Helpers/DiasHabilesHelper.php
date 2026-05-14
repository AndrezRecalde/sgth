<?php

namespace App\Helpers;

use App\Models\Asistencia\FeriadoInstitucional;
use Carbon\Carbon;

trait DiasHabilesHelper
{
    protected function calcularDiasHabiles(
        Carbon $fechaInicio,
        int $dias
    ): Carbon {
        $fecha = $fechaInicio->copy();
        $diasSumados = 0;

        while ($diasSumados < $dias) {
            $fecha->addDay();

            if ($fecha->isWeekend()) {
                continue;
            }

            $esFeriado = FeriadoInstitucional::esFeriado($fecha)->exists();
            if ($esFeriado) {
                continue;
            }

            $diasSumados++;
        }

        return $fecha;
    }
}
