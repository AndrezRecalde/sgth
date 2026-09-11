<?php

namespace App\Services\Asistencia\Motores;

use App\Contracts\Asistencia\VacacionMotorInterface;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\EscalaVacaciones;
use Carbon\Carbon;

class VacacionLosepService implements VacacionMotorInterface
{
    /**
     * LOSEP, art. 29: treinta días al año, sin escala por antigüedad.
     *
     * Aplicaba 15/20/25/30 según los años en el sector público, y con los años
     * en decimales: con 5 años y 4 meses daba 20. Confirmado con Talento Humano
     * el 2026-09-11: son treinta para todos, completos desde el primer año.
     */
    public function calcularDiasGanadosAnuales(Servidor $servidor): float
    {
        return EscalaVacaciones::DIAS_LOSEP;
    }

    public function validarLimitesAcumulacion(float $diasAcumuladosTotales, float $diasGanadosAnuales): array
    {
        // LOSEP: Límite estricto de acumulación = 60 días
        $respuesta = ['bloquear' => false, 'alerta' => false, 'mensaje' => ''];

        if ($diasAcumuladosTotales >= 60) {
            $respuesta['bloquear'] = true;
            $respuesta['mensaje'] = 'Límite legal superado: El servidor ha acumulado 60 o más días de vacaciones. LOSEP prohíbe acumular más. Debe gozarlas inmediatamente.';
        } elseif ($diasAcumuladosTotales >= 45) {
            $respuesta['alerta'] = true;
            $respuesta['mensaje'] = 'Alerta preventiva: El servidor está próximo al límite legal de 60 días de acumulación (LOSEP). Por favor planifique sus vacaciones.';
        }

        return $respuesta;
    }

    /**
     * Días calendario, incluidos ambos extremos: igual que el Código del Trabajo.
     *
     * Descontaba solo de lunes a viernes y sin feriados. Los treinta días del
     * art. 29 son calendario —confirmado con Talento Humano—, así que unas
     * vacaciones que abarcan un fin de semana lo consumen.
     */
    public function calcularDiasDescuento(Carbon $fechaInicio, Carbon $fechaFin): float
    {
        if ($fechaFin->lessThan($fechaInicio)) return 0;

        return $fechaInicio->diffInDays($fechaFin) + 1;
    }

    public function permiteCompensacionEfectivo(): bool
    {
        // LOSEP prohíbe compensación de vacaciones en activo
        return false;
    }
}
