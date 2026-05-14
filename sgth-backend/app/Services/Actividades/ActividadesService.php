<?php
namespace App\Services\Actividades;

use App\Contracts\Actividades\ActividadesServiceInterface;
use App\Models\Actividades\ActividadLaboral;
use App\Models\Actividades\InformeActividad;
use App\Services\Actividades\GenerarInformeActividadesService;
use Illuminate\Support\Facades\DB;

class ActividadesService implements ActividadesServiceInterface
{
    public function __construct(private readonly GenerarInformeActividadesService $informeService)
    {
    }

    public function registrarActividad(array $datos)
    {
        return ActividadLaboral::create($datos);
    }

    public function validarCruceBiometrico(int $servidorId, string $fecha): array
    {
        $marcaciones = DB::table('marcaciones')
            ->where('servidor_id', $servidorId)
            ->whereDate('fecha_hora', $fecha)
            ->count();

        $actividades = ActividadLaboral::where('servidor_id', $servidorId)
            ->where('fecha', $fecha)
            ->count();

        $alerta = false;
        if ($marcaciones > 0 && $actividades === 0) {
            $alerta = true;
        }

        return [
            'marcaciones' => $marcaciones,
            'actividades' => $actividades,
            'alerta_sin_documentar' => $alerta
        ];
    }

    public function generarInformeMensual(int $servidorId, int $mes, int $anio): InformeActividad
    {
        return DB::transaction(function () use ($servidorId, $mes, $anio) {
            $pdfUrl = $this->informeService->generarPdf($servidorId, $mes, $anio);

            return InformeActividad::updateOrCreate(
                ['servidor_id' => $servidorId, 'mes' => $mes, 'anio' => $anio],
                ['url_pdf' => $pdfUrl, 'estado' => 'generado']
            );
        });
    }
}