<?php

namespace App\Console\Commands;

use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\PeriodoVacacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula los períodos de vacaciones ABIERTOS con la escala legal.
 *
 * Talento Humano confirmó el 2026-09-11 la escala de cada régimen —LOSEP
 * art. 29: treinta días; Código del Trabajo art. 69: quince, más uno por cada
 * año que exceda de cinco— y autorizó recalcular los períodos abiertos. Los
 * generados con la escala anterior tenían de menos (LOSEP) o de más (Código
 * del Trabajo entre el segundo y el quinto año).
 *
 * - Solo los abiertos. Un período cerrado tiene su saldo certificado; para
 *   corregir uno está el recálculo forzado, período por período.
 * - Cambia lo generado y, con eso, el saldo. Lo gozado y lo vencido no se
 *   tocan: ocurrieron.
 * - Cada período que cambia queda en la bitácora con el antes, el después y
 *   quién autorizó el recálculo.
 *
 * Primero con --simular, para revisar qué cambiaría.
 */
class AplicarEscalaVacacionesCommand extends Command
{
    protected $signature = 'sgth:vacaciones:aplicar-escala-legal
        {--simular : Muestra qué cambiaría, sin guardar nada}
        {--responsable= : Quién autorizó el recálculo; queda en la bitácora}';

    protected $description = 'Recalcula los períodos de vacaciones abiertos con la escala legal (LOSEP art. 29, Código del Trabajo art. 69).';

    public function handle(PeriodoVacacionService $periodos): int
    {
        $simular     = (bool) $this->option('simular');
        $responsable = trim((string) $this->option('responsable'));

        if (! $simular && $responsable === '') {
            $this->error(
                'Indique quién autorizó el recálculo con --responsable="…": queda en la bitácora. '
                .'Para ver los cambios sin guardarlos, use --simular.'
            );

            return self::FAILURE;
        }

        // Por servidor y del año más antiguo al más nuevo: el acumulado de cada
        // período se arma con el saldo de los anteriores.
        $abiertos = PeriodoVacacion::where('estado', 'abierto')
            ->orderBy('servidor_id')
            ->orderBy('anio')
            ->get();

        $filas   = [];
        $tocados = [];

        DB::transaction(function () use ($abiertos, $periodos, $simular, $responsable, &$filas, &$tocados) {
            foreach ($abiertos as $periodo) {
                $servidor = Servidor::find($periodo->servidor_id);

                if (! $servidor) {
                    continue;
                }

                $anio   = (int) $periodo->anio;
                $cifras = $periodos->calcularCifras($servidor, $anio, $periodo);
                $antes  = (float) $periodo->dias_generados;

                if (abs($cifras['dias_generados'] - $antes) < 0.005) {
                    continue;
                }

                $filas[] = [
                    $servidor->cedula,
                    trim("{$servidor->apellido} {$servidor->nombre}"),
                    $cifras['regimen'],
                    $anio,
                    number_format($antes, 2),
                    number_format($cifras['dias_generados'], 2),
                    number_format((float) $periodo->dias_saldo, 2),
                    number_format($cifras['dias_saldo'], 2),
                ];

                if ($simular) {
                    continue;
                }

                $registroAntes = $periodo->only([
                    'dias_generados', 'dias_utilizados', 'dias_saldo', 'anios_antiguedad', 'regimen',
                ]);

                $nuevo = $periodos->generarPeriodo($servidor, $anio);
                $tocados[$servidor->id] = true;

                activity('periodos-vacaciones')
                    ->performedOn($nuevo)
                    ->withProperties([
                        'anio'        => $anio,
                        'responsable' => $responsable,
                        'origen'      => $this->getName(),
                        'antes'       => $registroAntes,
                        'despues'     => $nuevo->only([
                            'dias_generados', 'dias_utilizados', 'dias_saldo', 'anios_antiguedad', 'regimen',
                        ]),
                    ])
                    ->log('Escala legal de vacaciones aplicada');
            }

            // Un cambio en un año mueve el acumulado de los siguientes.
            foreach (array_keys($tocados) as $servidorId) {
                $periodos->recalcularAcumulados($servidorId);
            }
        });

        if ($filas === []) {
            $this->info('Todos los períodos abiertos ya siguen la escala legal: no hay nada que cambiar.');

            return self::SUCCESS;
        }

        $this->table(
            ['Cédula', 'Servidor', 'Régimen', 'Año', 'Generados antes', 'Generados ahora', 'Saldo antes', 'Saldo ahora'],
            $filas
        );

        $this->info($simular
            ? count($filas).' período(s) cambiarían. No se guardó nada: ejecute sin --simular para aplicarlo.'
            : count($filas).' período(s) recalculados. Quedaron en la bitácora a nombre de «'.$responsable.'».'
        );

        return self::SUCCESS;
    }
}
