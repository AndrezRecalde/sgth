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