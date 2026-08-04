<?php

namespace App\Services\Reporte;

use App\Contracts\Expediente\ExpedienteServiceInterface;
use App\Contracts\Reporte\ReporteSiithSutServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Reporte\ConfiguracionReporteMovimiento;
use Illuminate\Support\Collection;

class ReporteSiithSutService implements ReporteSiithSutServiceInterface
{
    /**
     * A qué regimen_juridico (el que ya resuelve
     * ExpedienteService::lineaDeTiempo()) corresponde cada portal. Un
     * vínculo de servicios profesionales resuelve a
     * 'codigo_civil_losncp' — no coincide con ninguno de los dos, así
     * que nunca entra a ninguno de los dos reportes.
     */
    private const REGIMEN_POR_PORTAL = [
        'siith' => 'losep',
        'sut'   => 'codigo_trabajo',
    ];

    public function __construct(
        private readonly ExpedienteServiceInterface $expedienteService,
    ) {
    }

    public function movimientosReportables(array $filtros): Collection
    {
        $portal = $filtros['portal'] ?? null;

        if (!isset(self::REGIMEN_POR_PORTAL[$portal])) {
            throw new ReglaNegocioException("Portal '{$portal}' no reconocido. Use 'siith' o 'sut'.");
        }

        $regimenEsperado = self::REGIMEN_POR_PORTAL[$portal];
        $columnaReportable = $portal === 'siith' ? 'reportable_siith' : 'reportable_sut';

        $tiposReportables = ConfiguracionReporteMovimiento::where($columnaReportable, true)
            ->pluck('tipo_movimiento')
            ->all();

        if (empty($tiposReportables)) {
            return collect();
        }

        $servidorIds = $this->resolverServidorIds($filtros, $tiposReportables);

        $resultados = collect();

        // Se recorre por servidor, reutilizando lineaDeTiempo() (no se
        // reimplementa la resolución de régimen aquí).
        foreach ($servidorIds as $servidorId) {
            $linea = $this->expedienteService->lineaDeTiempo($servidorId);

            $reportables = $linea
                ->filter(function (array $item) use ($tiposReportables, $regimenEsperado, $filtros) {
                    if ($item['tipo'] !== 'evento') {
                        return false;
                    }
                    if (!in_array($item['estado'], ['registrada', 'notificada'], true)) {
                        return false;
                    }
                    if (!in_array($item['tipo_movimiento'], $tiposReportables, true)) {
                        return false;
                    }
                    if ($item['regimen_juridico'] !== $regimenEsperado) {
                        return false;
                    }
                    if (!empty($filtros['desde']) && $item['fecha'] < $filtros['desde']) {
                        return false;
                    }
                    if (!empty($filtros['hasta']) && $item['fecha'] > $filtros['hasta']) {
                        return false;
                    }

                    return true;
                })
                ->map(fn (array $item) => array_merge($item, ['servidor_id' => $servidorId]));

            $resultados = $resultados->merge($reportables);
        }

        return $resultados->sortBy('fecha')->values();
    }

    public function reporteMensual(int $anio, int $mes, string $portal): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));

        $detalle = $this->movimientosReportables([
            'portal' => $portal,
            'desde'  => $desde,
            'hasta'  => $hasta,
        ]);

        return [
            'periodo' => sprintf('%04d-%02d', $anio, $mes),
            'portal'  => $portal,
            'resumen' => $detalle->countBy('tipo_movimiento')->all(),
            'detalle' => $detalle->values()->all(),
        ];
    }

    /**
     * Servidores candidatos: si se pide uno específico, solo ese. Si no,
     * todos los que tengan al menos un MovimientoPersonal
     * registrada/notificada de un tipo reportable dentro del rango — para
     * no recorrer lineaDeTiempo() de servidores sin nada relevante.
     */
    private function resolverServidorIds(array $filtros, array $tiposReportables): array
    {
        if (!empty($filtros['servidor_id'])) {
            return [(int) $filtros['servidor_id']];
        }

        $query = MovimientoPersonal::query()
            ->whereIn('estado', ['registrada', 'notificada'])
            ->whereIn('tipo_movimiento', $tiposReportables);

        if (!empty($filtros['desde'])) {
            $query->where('fecha_efectiva', '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $query->where('fecha_efectiva', '<=', $filtros['hasta']);
        }

        return $query->distinct()->pluck('servidor_id')->all();
    }
}
