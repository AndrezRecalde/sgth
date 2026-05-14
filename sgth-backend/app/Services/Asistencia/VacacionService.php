<?php

namespace App\Services\Asistencia;

use App\Contracts\Asistencia\VacacionMotorInterface;
use App\Contracts\Asistencia\VacacionServiceInterface;
use App\Enums\RegimenLaboral;
use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\Motores\VacacionCodigoTrabajoService;
use App\Services\Asistencia\Motores\VacacionLosepService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VacacionService implements VacacionServiceInterface
{
    public function obtenerMotor(Servidor $servidor): VacacionMotorInterface
    {
        // Factoría dinámica según la jurisprudencia aplicable al servidor
        if ($servidor->regimen_laboral === RegimenLaboral::CODIGO_TRABAJO) {
            return new VacacionCodigoTrabajoService();
        }

        // Por defecto, o si es LOSEP
        return new VacacionLosepService();
    }

    public function calcularSaldoActual(int $servidorId): float
    {
        $servidor = Servidor::findOrFail($servidorId);
        $motor = $this->obtenerMotor($servidor);

        // Lógica simplificada: (Días Ganados Históricos) - (Días Gozados/Aprobados)
        // En una app real, aquí se sumarían todos los periodos anuales.
        // Para este entregable, simularemos el saldo base asumiendo que no ha gastado nada
        // y usando la función de ganancia anual multiplicada por años de servicio.
        
        $fechaIngreso = $servidor->fecha_ingreso_institucion ?? now();
        $aniosCompletos = max(1, $fechaIngreso->diffInYears(now()));
        
        $diasGanadosPorAnio = $motor->calcularDiasGanadosAnuales($servidor);
        $diasAcumuladosTotales = $diasGanadosPorAnio * $aniosCompletos;

        // Restar las ya gozadas o aprobadas (para no sobregirar el saldo)
        $diasGastados = Vacacion::where('servidor_id', $servidor->id)
            ->whereIn('estado', ['aprobada', 'gozada'])
            ->sum('dias_solicitados');

        return max(0, $diasAcumuladosTotales - $diasGastados);
    }

    public function solicitar(array $datos, int $servidorId): Vacacion
    {
        return DB::transaction(function () use ($datos, $servidorId) {
            $servidor = Servidor::findOrFail($servidorId);
            $motor = $this->obtenerMotor($servidor);

            $fechaInicio = Carbon::parse($datos['fecha_inicio']);
            $fechaFin = Carbon::parse($datos['fecha_fin']);

            if ($fechaFin->lessThan($fechaInicio)) {
                throw new \Exception("La fecha de fin no puede ser menor a la fecha de inicio.");
            }

            // 1. Calcular exactamente cuántos días le costará esta vacación
            $diasADescontar = $motor->calcularDiasDescuento($fechaInicio, $fechaFin);

            if ($diasADescontar <= 0) {
                throw new \Exception("Las fechas seleccionadas no representan días laborables descontables.");
            }

            // 2. Verificar saldo disponible
            $saldoActual = $this->calcularSaldoActual($servidorId);

            if ($diasADescontar > $saldoActual) {
                throw new \Exception("Saldo insuficiente. Intentas solicitar {$diasADescontar} días, pero tu saldo es de {$saldoActual} días.");
            }

            // 3. Validar límites legales (Alerta / Bloqueo)
            $diasGanadosPorAnio = $motor->calcularDiasGanadosAnuales($servidor);
            $limites = $motor->validarLimitesAcumulacion($saldoActual, $diasGanadosPorAnio);

            // Si supera el límite de 60 días LOSEP o 3 años CT, se le fuerza a salir pero no bloqueamos
            // la SOLICITUD (al revés, es obligatorio que solicite). Si quisiéramos bloquear se haría aquí.
            
            // 4. Crear la solicitud en estado PENDIENTE
            return Vacacion::create([
                'servidor_id'      => $servidorId,
                'fecha_inicio'     => $fechaInicio,
                'fecha_fin'        => $fechaFin,
                'dias_solicitados' => $diasADescontar,
                'tipo_dias'        => $servidor->regimen_laboral === RegimenLaboral::CODIGO_TRABAJO ? 'calendario' : 'habiles',
                'estado'           => 'pendiente',
            ]);
        });
    }
}
