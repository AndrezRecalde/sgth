<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Contracts\Reporteria\ReporteriaServiceInterface;
use Spatie\LaravelPdf\Facades\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReporteGenericoExport;
use App\Helpers\DiasHabilesHelper;
use Illuminate\Support\Facades\Log;

class GenerarReportesLotaipCommand extends Command
{
    use DiasHabilesHelper;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lotaip:generar-reportes {--force : Forzar la generación ignorando la fecha}';

    /**
     * The console command description.
     */
    protected $description = 'Genera y publica automáticamente los reportes obligatorios de Transparencia LOTAIP Art. 7';

    /**
     * Execute the console command.
     */
    public function handle(ReporteriaServiceInterface $reporteriaService): int
    {
        $this->info('Iniciando generación de reportes LOTAIP...');

        $hoy = now();
        
        // Excelente observación: Usamos el Trait del Sprint 8 para saltar Feriados Nacionales
        // Partimos desde el último día del mes anterior y le sumamos 1 día hábil
        $ultimoDiaMesAnterior = $hoy->copy()->startOfMonth()->subDay();
        $primerDiaHabil = $this->calcularDiasHabiles($ultimoDiaMesAnterior, 1);

        if (!$hoy->isSameDay($primerDiaHabil) && !$this->option('force')) {
            $this->info("Hoy ({$hoy->toDateString()}) no es el primer día hábil del mes. El primer día hábil es {$primerDiaHabil->toDateString()}. Abortando ejecución.");

            return self::SUCCESS;
        }

        $mesAnio = $hoy->format('Y_m');

        try {
            // 1. Distributivo de Sueldos (Mensual)
            $this->info('Generando Distributivo de Sueldos...');
            $distributivo = $reporteriaService->generarDistributivoSueldos(['mes' => $hoy->month, 'anio' => $hoy->year]);
            Excel::store(new ReporteGenericoExport($distributivo['datos']), "public/lotaip/distributivo_{$mesAnio}.xlsx");

            // 2. Nómina Consolidada (Mensual)
            $this->info('Generando Nómina Consolidada...');
            $nomina = $reporteriaService->generarNominaConsolidada(['mes' => $hoy->month, 'anio' => $hoy->year]);
            Excel::store(new ReporteGenericoExport($nomina['datos']), "public/lotaip/nomina_{$mesAnio}.xlsx");

            // 3. Estructura Orgánica Actualizada
            $this->info('Generando Estructura Orgánica...');
            // Utilizamos el ad hoc del Módulo de Estructura (simulado)
            $estructura = $reporteriaService->generarReporteAdHoc(['modulo' => 'estructura_organica', 'parametros' => []]);
            Pdf::view('reportes.generico', [
                'datos' => $estructura['datos'],
                'metadata' => ['reporte' => 'Estructura Orgánica Institucional - LOTAIP', 'fecha' => $hoy->toDateString()]
            ])->save(storage_path("app/public/lotaip/estructura_{$mesAnio}.pdf"));

            $this->info('Reportes LOTAIP generados y publicados exitosamente en storage/app/public/lotaip/.');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            // El fallo moría aquí, escrito solo en la consola: en una tarea
            // programada a la una de la madrugada eso equivale a no decir nada,
            // y el planificador la daba por buena. Lleva fallando desde
            // siempre sin que constara en ningún sitio.
            //
            // Ahora queda en el registro y el comando termina con código de
            // error, para que quien vigile las tareas lo vea. Se devuelve
            // FAILURE en vez de relanzar: el fallo ya está anotado con su
            // detalle, y lo que necesita quien vigila es el código de salida,
            // no una traza suelta en la consola del planificador.
            Log::error('No se pudieron generar los reportes LOTAIP.', [
                'excepcion' => $e->getMessage(),
                'archivo'   => $e->getFile() . ':' . $e->getLine(),
            ]);

            $this->error('Error generando reportes LOTAIP: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
