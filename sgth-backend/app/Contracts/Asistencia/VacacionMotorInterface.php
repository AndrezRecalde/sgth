<?php

namespace App\Contracts\Asistencia;

use App\Models\Expediente\Servidor;
use Carbon\Carbon;

interface VacacionMotorInterface
{
    /**
     * Calcula la cantidad de días anuales a los que tiene derecho el servidor
     * basándose en sus años de servicio y el régimen laboral.
     */
    public function calcularDiasGanadosAnuales(Servidor $servidor): float;

    /**
     * Valida los límites legales de acumulación de vacaciones.
     * Retorna un arreglo con estados de alerta o bloqueo:
     * ['bloquear' => bool, 'alerta' => bool, 'mensaje' => string]
     */
    public function validarLimitesAcumulacion(float $diasAcumuladosTotales, float $diasGanadosAnuales): array;

    /**
     * Calcula la cantidad exacta de días que deben descontarse del saldo
     * para el rango de fechas solicitado (Hábiles vs Calendario).
     */
    public function calcularDiasDescuento(Carbon $fechaInicio, Carbon $fechaFin): float;

    /**
     * Indica si la ley permite compensar económicamente parte de las vacaciones.
     */
    public function permiteCompensacionEfectivo(): bool;
}
