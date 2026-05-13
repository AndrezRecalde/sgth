<?php

namespace App\Jobs\Reporteria;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Contracts\Reporteria\ReporteriaServiceInterface;
use App\Models\Expediente\Servidor;

class GenerarReporteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $jobId,
        private readonly int $servidorId,
        private readonly string $tipoReporte,
        private readonly array $parametros
    ) {
        $this->onQueue('reportes');
    }

    /**
     * Execute the job.
     */
    public function handle(ReporteriaServiceInterface $reporteriaService): void
    {
        // 1. Actualizar estado a procesando
        $this->updateStatus('procesando', 20);

        try {
            // 2. Ejecutar la generación del reporte pesado (delegado al service)
            // Se asume que el servicio puede devolver la URL del archivo final
            $this->updateStatus('procesando', 50);
            
            // Simulación de carga pesada
            sleep(2); 
            
            // En la Tarea 5 y 6 esto llamará a los exportadores reales de Excel/PDF
            $resultado = match ($this->tipoReporte) {
                'distributivo_sueldos' => $reporteriaService->generarDistributivoSueldos($this->parametros),
                'nomina_consolidada' => $reporteriaService->generarNominaConsolidada($this->parametros),
                'planilla_iess' => $reporteriaService->generarPlanillaIess($this->parametros),
                'formulario_107_sri' => $reporteriaService->generarFormulario107($this->parametros),
                'informe_pac' => $reporteriaService->generarInformePac($this->parametros),
                'reporte_asistencia' => $reporteriaService->generarReporteAsistencia($this->parametros),
                'reporte_viaticos' => $reporteriaService->generarReporteViaticos($this->parametros),
                'reporte_accidentabilidad' => $reporteriaService->generarReporteAccidentabilidad($this->parametros),
                default => $reporteriaService->generarReporteAdHoc([
                    'modulo' => $this->tipoReporte,
                    'parametros' => $this->parametros
                ]),
            };
            
            $formato = $this->parametros['formato'] ?? 'excel';
            $fileName = 'reporte_' . $this->jobId;
            $urlDescarga = null;

            if ($formato === 'pdf') {
                $fileName .= '.pdf';
                // Usando Spatie Laravel PDF
                \Spatie\LaravelPdf\Facades\Pdf::view('reportes.generico', [
                    'datos' => $resultado['datos'],
                    'metadata' => $resultado['metadata'] ?? []
                ])->save(storage_path('app/public/reportes/' . $fileName));
                $urlDescarga = '/storage/reportes/' . $fileName;
            } else {
                $fileName .= '.xlsx';
                // Usando Maatwebsite Excel
                \Maatwebsite\Excel\Facades\Excel::store(
                    new \App\Exports\ReporteGenericoExport($resultado['datos']), 
                    'public/reportes/' . $fileName
                );
                $urlDescarga = '/storage/reportes/' . $fileName;
            }

            // 3. Actualizar estado a completado con la URL
            $this->updateStatus('completado', 100, $urlDescarga);

            // 4. Notificar al usuario por correo
            $servidor = Servidor::find($this->servidorId);
            if ($servidor && $servidor->correo_institucional) {
                // Mock de envío de correo
                // Mail::to($servidor->correo_institucional)->send(new ReporteTerminadoMail($urlDescarga));
            }

        } catch (\Exception $e) {
            // Manejo de fallos
            $this->updateStatus('error', 0, null, $e->getMessage());
        }
    }

    private function updateStatus(string $estado, int $progreso, ?string $urlDescarga = null, ?string $error = null): void
    {
        Cache::put("sgth:reporte_job:{$this->jobId}", [
            'estado' => $estado,
            'progreso' => $progreso,
            'url_descarga' => $urlDescarga,
            'error' => $error,
            'actualizado_en' => now()->toDateTimeString()
        ], now()->addHours(24)); // Mantener el estado por 24 horas
    }
}
