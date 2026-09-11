<?php

namespace App\Services\Asistencia\Motores;

use App\Contracts\Asistencia\VacacionMotorInterface;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\EscalaVacaciones;
use Carbon\Carbon;

class VacacionCodigoTrabajoService implements VacacionMotorInterface
{
    public function calcularDiasGanadosAnuales(Servidor $servidor): float
    {
        // Código del Trabajo considera antigüedad en el EMPLEADOR ACTUAL (GAD)
        $fechaIngreso = $servidor->fecha_ingreso_institucion;
        
        if (!$fechaIngreso) return 15;

        $aniosServicio = (int) floor($fechaIngreso->diffInYears(now()));

        // Art. 69: la misma escala que los períodos, desde un solo sitio.
        return EscalaVacaciones::diasGenerados(RegimenLaboral::CODIGO_TRABAJO->value, $aniosServicio);
    }

    public function validarLimitesAcumulacion(float $diasAcumuladosTotales, float $diasGanadosAnuales): array
    {
        // CT: Límite de acumulación es de 3 años. 
        // Si el empleado gana ej. 15 días al año, el límite son 45 días.
        $limiteTresAnios = $diasGanadosAnuales * 3;
        $limiteDosAnios = $diasGanadosAnuales * 2;

        $respuesta = ['bloquear' => false, 'alerta' => false, 'mensaje' => ''];

        if ($diasAcumuladosTotales >= $limiteTresAnios) {
            $respuesta['bloquear'] = true;
            $respuesta['mensaje'] = "Límite superado: El Código del Trabajo prohíbe acumular más de 3 años de vacaciones ({$limiteTresAnios} días en su caso).";
        } elseif ($diasAcumuladosTotales >= $limiteDosAnios) {
            $respuesta['alerta'] = true;
            $respuesta['mensaje'] = "Alerta preventiva: Acercándose al límite de 3 años de acumulación de vacaciones (CT).";
        }

        return $respuesta;
    }

    public function calcularDiasDescuento(Carbon $fechaInicio, Carbon $fechaFin): float
    {
        if ($fechaFin->lessThan($fechaInicio)) return 0;

        // CT descuenta en días CALENDARIO (incluyendo fines de semana)
        return $fechaInicio->diffInDays($fechaFin) + 1;
    }

    public function permiteCompensacionEfectivo(): bool
    {
        // CT sí permite compensar económicamente hasta la mitad con mutuo acuerdo
        return true;
    }
}
