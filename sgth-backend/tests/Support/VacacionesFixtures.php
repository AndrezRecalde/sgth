<?php

use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Expediente\Servidor;
use Illuminate\Support\Carbon;

/**
 * Un período de vacaciones abierto y con saldo, del año en curso.
 *
 * Hasta la fase 5.1 las pruebas de vacaciones pedían «matrimonio», que no
 * descuenta, para no necesitar período. Ya no se pide: la solicitud es solo de
 * vacaciones, y las licencias tienen su pantalla.
 */
function periodoVacacionesDePrueba(Servidor $servidor, float $dias = 30): PeriodoVacacion
{
    $anio = now()->year;

    return PeriodoVacacion::create([
        'servidor_id'          => $servidor->id,
        'anio'                 => $anio,
        'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
        'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
        'regimen'              => 'losep',
        'anios_antiguedad'     => 5,
        'dias_generados'       => $dias,
        'dias_utilizados'      => 0,
        'dias_saldo'           => $dias,
        'saldo_acumulado'      => $dias,
        'estado'               => 'abierto',
    ]);
}
