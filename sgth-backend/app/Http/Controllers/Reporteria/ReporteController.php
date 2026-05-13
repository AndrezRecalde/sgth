<?php

namespace App\Http\Controllers\Reporteria;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Reporteria\ConfiguracionReporte;
use App\Contracts\Reporteria\ReporteriaServiceInterface;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function __construct(private readonly ReporteriaServiceInterface $service)
    {
    }

    public function indexConfiguraciones(Request $request)
    {
        $usuarioId = $request->user()->servidor_id ?? 1; // En entorno real viene del Auth Sanctum
        
        $configuraciones = ConfiguracionReporte::where('creado_por_id', $usuarioId)->latest()->get();
        return ApiResponse::ok($configuraciones, 'Configuraciones de reportes ad hoc listadas exitosamente.');
    }

    public function storeConfiguracion(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'modulo' => 'required|string|max:50',
            'parametros' => 'required|array'
        ]);

        $validated['creado_por_id'] = $request->user()->servidor_id ?? 1; // Asumiendo servidor logueado

        $configuracion = ConfiguracionReporte::create($validated);
        return ApiResponse::created($configuracion, 'Configuración de reporte ad hoc guardada.');
    }

    public function generarAdHoc(Request $request)
    {
        $validated = $request->validate([
            'configuracion_id' => 'nullable|exists:configuraciones_reporte,id',
            'modulo' => 'required_without:configuracion_id|string|max:50',
            'parametros' => 'required_without:configuracion_id|array'
        ]);

        if (isset($validated['configuracion_id'])) {
            $config = ConfiguracionReporte::findOrFail($validated['configuracion_id'])->toArray();
        } else {
            $config = [
                'modulo' => $validated['modulo'],
                'parametros' => $validated['parametros']
            ];
        }

        // Delegar al servicio la generación dinámica de los datos del reporte ad hoc
        $reporteData = $this->service->generarReporteAdHoc($config);

        return ApiResponse::ok($reporteData, 'Reporte ad hoc generado exitosamente.');
    }

    public function solicitarFondo(Request $request)
    {
        $validated = $request->validate([
            'tipo_reporte' => 'required|string|max:100',
            'parametros' => 'nullable|array'
        ]);

        $usuarioId = $request->user()->servidor_id ?? 1;
        $jobId = uniqid('rep_');

        // Inicializar estado en Redis
        \Illuminate\Support\Facades\Cache::put("sgth:reporte_job:{$jobId}", [
            'estado' => 'en_cola',
            'progreso' => 0,
            'url_descarga' => null,
            'error' => null,
            'actualizado_en' => now()->toDateTimeString()
        ], now()->addHours(24));

        // Despachar a la cola 'reportes'
        \App\Jobs\Reporteria\GenerarReporteJob::dispatch(
            $jobId,
            $usuarioId,
            $validated['tipo_reporte'],
            $validated['parametros'] ?? []
        );

        return ApiResponse::ok([
            'job_id' => $jobId,
            'mensaje' => 'El reporte se está procesando en segundo plano. Puede consultar el estado con el job_id.'
        ], 'Reporte encolado exitosamente.');
    }

    public function estadoFondo(string $jobId)
    {
        $estado = \Illuminate\Support\Facades\Cache::get("sgth:reporte_job:{$jobId}");

        if (!$estado) {
            return ApiResponse::error('El Job ID proporcionado no existe o ha expirado.', 404);
        }

        return ApiResponse::ok($estado, 'Estado del reporte recuperado.');
    }
}
