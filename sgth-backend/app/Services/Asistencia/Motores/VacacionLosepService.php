<?php

namespace App\Services\Asistencia\Motores;

use App\Contracts\Asistencia\VacacionMotorInterface;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;

class VacacionLosepService implements VacacionMotorInterface
{
    public function calcularDiasGanadosAnuales(Servidor $servidor): float
    {
        // LOSEP considera antigüedad en el SECTOR PÚBLICO general
        $fechaIngreso = $servidor->fecha_ingreso_sector_publico ?? $servidor->fecha_ingreso_institucion;
        
        if (!$fechaIngreso) return 15; // Por defecto si no hay data

        $aniosServicio = $fechaIngreso->diffInYears(now());

        if ($aniosServicio <= 5) {
            return 15;
        } elseif ($aniosServicio <= 10) {
            return 20;
        } elseif ($aniosServicio <= 15) {
            return 25;
        } else {
            return 30;
        }
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

    public function calcularDiasDescuento(Carbon $fechaInicio, Carbon $fechaFin): float
    {
        if ($fechaFin->lessThan($fechaInicio)) return 0;

        // LOSEP descuenta en días HÁBILES
        $dias = 0;
        $actual = $fechaInicio->copy();

        while ($actual->lessThanOrEqualTo($fechaFin)) {
            if (!$actual->isWeekend()) {
                $dias++;
            }
            $actual->addDay();
        }

        return $dias;
    }

    public function permiteCompensacionEfectivo(): bool
    {
        // LOSEP prohíbe compensación de vacaciones en activo
        return false;
    }
}
