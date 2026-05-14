<?php
namespace App\Services\Bienestar;

use App\Contracts\Bienestar\BienestarServiceInterface;
use App\Models\Bienestar\ResultadoClima;
use Illuminate\Support\Facades\DB;

class BienestarService implements BienestarServiceInterface
{
    public function registrarRespuestaAnonima(array $datos): void
    {
        // Garantizamos que aunque el request traiga servidor_id, NUNCA se guarde
        unset($datos['servidor_id']);
        unset($datos['user_id']);

        ResultadoClima::create($datos);
    }

    public function obtenerResultadosAgregadosPorUnidad(int $encuestaId, int $unidadId): array
    {
        return ResultadoClima::where('encuesta_id', $encuestaId)
            ->where('unidad_administrativa_id', $unidadId)
            ->select(
                DB::raw('AVG(liderazgo) as prom_liderazgo'),
                DB::raw('AVG(comunicacion) as prom_comunicacion'),
                DB::raw('AVG(trabajo_en_equipo) as prom_equipo'),
                DB::raw('AVG(condiciones_trabajo) as prom_condiciones'),
                DB::raw('AVG(desarrollo_profesional) as prom_desarrollo'),
                DB::raw('AVG(reconocimiento) as prom_reconocimiento'),
                DB::raw('AVG(satisfaccion_general) as prom_satisfaccion'),
                DB::raw('COUNT(id) as total_respuestas')
            )
            ->first()
            ->toArray();
    }
}