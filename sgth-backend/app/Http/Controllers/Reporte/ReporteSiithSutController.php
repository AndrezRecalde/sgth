<?php

namespace App\Http\Controllers\Reporte;

use App\Contracts\Reporte\ReporteSiithSutServiceInterface;
use App\Enums\ClaseAccionPersonal;
use App\Exports\ReporteSiithSutExport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El reporte de apoyo a la transcripción al SIITH y al SUT (fase 7.5): los
 * actos del mes, el Excel, la marca de transcrito y qué clase se reporta.
 */
class ReporteSiithSutController extends Controller
{
    public function __construct(
        private readonly ReporteSiithSutServiceInterface $reporteService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $f = $this->filtros($request);

        return ApiResponse::ok($this->reporteService->reporte($f['portal'], $f['periodo'], $f['situacion']), 'Reporte SIITH/SUT.');
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $f = $this->filtros($request);

        return Excel::download(
            new ReporteSiithSutExport($this->reporteService->filasDelExcel($f['portal'], $f['periodo'], $f['situacion'])),
            "reporte_{$f['portal']}_{$f['periodo']}.xlsx",
        );
    }

    /** Marca como transcritos los actos, o su anulación. */
    public function transcribir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'portal'           => ['required', 'in:siith,sut'],
            'movimiento_ids'   => ['required', 'array', 'min:1', 'max:500'],
            'movimiento_ids.*' => ['integer', 'distinct'],
        ], [], ['portal' => 'portal', 'movimiento_ids' => 'actos']);

        $hechas = $this->reporteService->transcribir($datos['portal'], $datos['movimiento_ids'], $request->user());

        return ApiResponse::ok(['transcritos' => $hechas], $hechas === 1 ? 'Se marcó 1 acto como transcrito.' : "Se marcaron {$hechas} actos como transcritos.");
    }

    public function configuracion(): JsonResponse
    {
        return ApiResponse::ok($this->reporteService->configuracion(), 'Qué clase se reporta a cada portal.');
    }

    public function actualizarConfiguracion(Request $request, string $clase): JsonResponse
    {
        $marcas = $request->validate([
            'reportable_siith' => ['sometimes', 'boolean'],
            'reportable_sut'   => ['sometimes', 'boolean'],
        ]);

        $clase = ClaseAccionPersonal::tryFrom($clase) ?? abort(404);

        return ApiResponse::ok(
            $this->reporteService->actualizarConfiguracion($clase, $marcas, $request->user()),
            "Se actualizó qué se reporta de «{$clase->etiqueta()}».",
        );
    }

    /** @return array{portal: string, periodo: string, situacion: string} */
    private function filtros(Request $request): array
    {
        $datos = $request->validate([
            'portal'    => ['required', 'in:siith,sut'],
            'periodo'   => ['required', 'date_format:Y-m'],
            'situacion' => ['nullable', 'in:pendientes,transcritos,todos'],
        ]);

        return ['portal' => $datos['portal'], 'periodo' => $datos['periodo'], 'situacion' => $datos['situacion'] ?? 'pendientes'];
    }
}
