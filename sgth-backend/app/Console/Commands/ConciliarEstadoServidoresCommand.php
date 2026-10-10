<?php

namespace App\Console\Commands;

use App\Models\Expediente\Servidor;
use App\Services\Expediente\ContratoServidorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pone el estado y la antigüedad de cada servidor de acuerdo con sus vínculos
 * (diseño de Acciones de Personal, 8.3 y 8.6 punto 6; fase 1.5).
 *
 * - `estado`: activo si tiene un vínculo vigente. Hasta la fase 1.5 nadie lo
 *   ponía en false, así que hay cesados que aparecen como activos —y entran en
 *   la nómina y en la generación de vacaciones—. Al apagarlos también se les
 *   quitan el puesto y la unidad, que el contrato ya no respalda.
 * - `fecha_ingreso_institucion`: el inicio del servicio continuo
 *   (Servidor::inicioDelServicioContinuo). Cada ingreso la pisaba, y una
 *   cesación seguida de un ingreso reiniciaba la antigüedad.
 *
 * Desde la fase 1.5 los dos se mantienen solos al cambiar un contrato; esto
 * corrige lo que quedó antes. Primero con --simular: sacar a alguien de la
 * nómina es una decisión que Talento Humano tiene que ver antes.
 */
class ConciliarEstadoServidoresCommand extends Command
{
    protected $signature = 'sgth:servidores:conciliar-estado
        {--simular : Muestra qué cambiaría, sin guardar nada}
        {--responsable= : Quién autorizó la conciliación; queda en la bitácora}';

    protected $description = 'Pone el estado y la antigüedad de cada servidor de acuerdo con sus vínculos laborales.';

    public function handle(ContratoServidorService $contratos): int
    {
        $simular     = (bool) $this->option('simular');
        $responsable = trim((string) $this->option('responsable'));

        if (! $simular && $responsable === '') {
            $this->error(
                'Indique quién autorizó la conciliación con --responsable="…": queda en la bitácora. '
                .'Para ver los cambios sin guardarlos, use --simular.'
            );

            return self::FAILURE;
        }

        $filas = [];

        DB::transaction(function () use ($contratos, $simular, $responsable, &$filas) {
            Servidor::orderBy('id')->each(function (Servidor $servidor) use ($contratos, $simular, $responsable, &$filas) {
                $estadoAntes  = (bool) $servidor->estado;
                $estadoAhora  = $servidor->tieneVinculoVigente();
                $ingresoAntes = $servidor->fecha_ingreso_institucion?->toDateString();
                $ingresoAhora = $servidor->inicioDelServicioContinuo();

                if ($estadoAntes === $estadoAhora && $ingresoAntes === $ingresoAhora) {
                    return;
                }

                $filas[] = [
                    $servidor->cedula,
                    trim("{$servidor->apellido} {$servidor->nombre}"),
                    $estadoAntes ? 'Activo' : 'Inactivo',
                    $estadoAhora ? 'Activo' : 'Inactivo',
                    $ingresoAntes ?? '—',
                    $ingresoAhora ?? '—',
                ];

                if ($simular) {
                    return;
                }

                // Por el modelo y no con un update masivo: ServidorObserver
                // deja el antes y el después en la auditoría de la ficha.
                if ($ingresoAntes !== $ingresoAhora) {
                    $servidor->update(['fecha_ingreso_institucion' => $ingresoAhora]);
                }

                // Estado, puesto y unidad salen juntos del vínculo vigente.
                $contratos->sincronizarPuestoDesdeVinculo($servidor->id);

                activity('servidores')
                    ->performedOn($servidor)
                    ->withProperties([
                        'responsable' => $responsable,
                        'origen'      => $this->getName(),
                        'antes'       => ['estado' => $estadoAntes, 'fecha_ingreso_institucion' => $ingresoAntes],
                        'despues'     => ['estado' => $estadoAhora, 'fecha_ingreso_institucion' => $ingresoAhora],
                    ])
                    ->log('Estado y antigüedad conciliados con los vínculos');
            });
        });

        if ($filas === []) {
            $this->info('El estado y la antigüedad de todos los servidores ya coinciden con sus vínculos.');

            return self::SUCCESS;
        }

        $this->table(
            ['Cédula', 'Servidor', 'Estado antes', 'Estado ahora', 'Ingreso antes', 'Ingreso ahora'],
            $filas
        );

        $this->info($simular
            ? count($filas).' servidor(es) cambiarían. No se guardó nada: ejecute sin --simular para aplicarlo.'
            : count($filas).' servidor(es) conciliados. Quedaron en la bitácora a nombre de «'.$responsable.'».'
        );

        return self::SUCCESS;
    }
}
