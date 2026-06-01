<?php
namespace App\Services\Asistencia;

use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PeriodoVacacionService
{
    /**
     * Calcula los días generados según régimen y antigüedad.
     */
    public function calcularDiasGenerados(
        string $regimen,
        int $aniosAntiguedad
    ): float {
        if ($regimen === 'losep') {
            return match(true) {
                $aniosAntiguedad >= 16 => 30.0,
                $aniosAntiguedad >= 11 => 25.0,
                $aniosAntiguedad >= 6  => 20.0,
                default                => 15.0,
            };
        }

        // Código del Trabajo: 15 + 1 por cada año adicional
        return min(15.0 + max(0, $aniosAntiguedad - 1), 30.0);
    }

    /**
     * Calcula la antigüedad según régimen:
     * LOSEP → desde fecha_ingreso_sector_publico
     * CT    → desde fecha_ingreso_institucion
     */
    public function calcularAntiguedad(
        Servidor $servidor,
        string $regimen,
        int $anio
    ): int {
        $fechaRef = $regimen === 'losep'
            ? $servidor->fecha_ingreso_sector_publico
            : $servidor->fecha_ingreso_institucion;

        if (!$fechaRef) return 0;

        return (int) Carbon::parse($fechaRef)
            ->diffInYears(Carbon::create($anio, 12, 31));
    }

    /**
     * Genera o actualiza el período de un servidor para un año.
     */
    public function generarPeriodo(
        Servidor $servidor,
        int $anio
    ): PeriodoVacacion {
        $regimen = $servidor->regimen_laboral instanceof \App\Enums\RegimenLaboral
            ? $servidor->regimen_laboral->value
            : (string)($servidor->regimen_laboral ?? 'losep');

        $antiguedad = $this->calcularAntiguedad($servidor, $regimen, $anio);
        $diasGen    = $this->calcularDiasGenerados($regimen, $antiguedad);

        // Calcular saldo acumulado de períodos anteriores abiertos
        $saldoAcumulado = PeriodoVacacion::where('servidor_id', $servidor->id)
            ->where('anio', '<', $anio)
            ->where('estado', 'abierto')
            ->sum('dias_saldo');

        // Aplicar límite de acumulación LOSEP (60 días)
        if ($regimen === 'losep') {
            $saldoAcumulado = min($saldoAcumulado, 60.0);
        }

        return PeriodoVacacion::updateOrCreate(
            [
                'servidor_id' => $servidor->id,
                'anio'        => $anio,
            ],
            [
                'fecha_inicio_periodo' => Carbon::create($anio, 1, 1),
                'fecha_fin_periodo'    => Carbon::create($anio, 12, 31),
                'regimen'              => $regimen,
                'anios_antiguedad'     => $antiguedad,
                'dias_generados'       => $diasGen,
                'dias_utilizados'      => 0,
                'dias_saldo'           => $diasGen,
                'saldo_acumulado'      => $saldoAcumulado + $diasGen,
                'estado'               => 'abierto',
            ]
        );
    }

    /**
     * Genera períodos para todos los servidores activos.
     * Llamado por el job anual.
     */
    public function generarPeriodosAnuales(int $anio): Collection
    {
        $servidores = Servidor::where('estado', true)->get();
        $resultados = collect();

        foreach ($servidores as $servidor) {
            try {
                $periodo = $this->generarPeriodo($servidor, $anio);
                $resultados->push($periodo);
            } catch (\Exception $e) {
                \Log::error(
                    "Error generando período {$anio} servidor {$servidor->id}: " .
                    $e->getMessage()
                );
            }
        }

        return $resultados;
    }

    /**
     * Descuenta días de un período al aprobar una vacación.
     */
    public function descontarDias(
        int $servidorId,
        float $dias,
        int $anio
    ): void {
        $periodo = PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('anio', $anio)
            ->where('estado', 'abierto')
            ->first();

        if (!$periodo) return;

        $periodo->dias_utilizados += $dias;
        $periodo->dias_saldo       = max(0, $periodo->dias_generados - $periodo->dias_utilizados);
        $periodo->saldo_acumulado  = max(0, $periodo->saldo_acumulado - $dias);

        // Verificar alerta LOSEP
        if ($periodo->debeAlertarLosep()) {
            $periodo->alerta_enviada = true;
            // Aquí se podría disparar un evento/notification
        }

        $periodo->save();
    }

    /**
     * Devuelve el saldo total disponible del servidor
     * sumando todos los períodos abiertos.
     */
    public function saldoTotal(int $servidorId): float
    {
        return (float) PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('estado', 'abierto')
            ->sum('dias_saldo');
    }

    /**
     * Obtiene el resumen de períodos de un servidor.
     */
    public function resumen(int $servidorId): array
    {
        $periodos = PeriodoVacacion::where('servidor_id', $servidorId)
            ->orderByDesc('anio')
            ->get();

        return [
            'periodos'        => $periodos,
            'saldo_total'     => $this->saldoTotal($servidorId),
            'alerta_limite'   => $this->saldoTotal($servidorId) >= 45,
        ];
    }
}
