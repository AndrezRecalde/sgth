<?php

namespace App\Http\Controllers\Tic;

use App\Exports\Tic\LibroReporteTic;
use App\Exports\Tic\ReportesTicExcel;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Tic\Reportes\ReporteInventarioService;
use App\Services\Tic\Reportes\ReporteObsolescenciaService;
use App\Services\Tic\Reportes\ReporteSatisfaccionService;
use App\Services\Tic\Reportes\ReporteTicketsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Los reportes de Tecnologías (fase 4.2). Cada uno se ve en pantalla y se
 * descarga en Excel o en PDF con `formato=excel|pdf`: los tres salen de la
 * misma consulta.
 */
final class ReporteTicController extends Controller
{
    public function inventario(Request $request, ReporteInventarioService $servicio): JsonResponse|BinaryFileResponse|Response
    {
        $filtros = $request->validate([
            'agrupar'                  => ['nullable', Rule::in(['unidad', 'ubicacion'])],
            'tipo_bien_id'             => ['nullable', 'integer'],
            'unidad_administrativa_id' => ['nullable', 'integer'],
            'ubicacion_id'             => ['nullable', 'integer'],
            ...$this->reglaFormato(),
        ]);
        $reporte = $servicio->generar($filtros);
        $porUbicacion = $reporte['agrupado_por'] === 'ubicacion';

        return $this->responder($request, $reporte, [
            'archivo'   => 'inventario-por-' . ($porUbicacion ? 'ubicacion' : 'unidad') . '-' . today()->toDateString(),
            'titulo'    => 'Inventario de equipos por ' . ($porUbicacion ? 'ubicación' : 'unidad'),
            'subtitulo' => 'Equipos principales en servicio al ' . today()->format('d/m/Y'),
            'vista'     => 'pdf.tic.inventario',
            'excel'     => fn () => ReportesTicExcel::inventario($reporte),
        ]);
    }

    public function obsolescencia(Request $request, ReporteObsolescenciaService $servicio): JsonResponse|BinaryFileResponse|Response
    {
        $filtros = $request->validate(['tipo_bien_id' => ['nullable', 'integer'], ...$this->reglaFormato()]);
        $reporte = $servicio->generar($filtros);

        return $this->responder($request, $reporte, [
            'archivo'   => 'obsolescencia-' . today()->toDateString(),
            'titulo'    => 'Obsolescencia de equipos',
            'subtitulo' => 'Vida útil cumplida o por cumplirse en los próximos 12 meses, al ' . today()->format('d/m/Y'),
            'vista'     => 'pdf.tic.obsolescencia',
            'excel'     => fn () => ReportesTicExcel::obsolescencia($reporte),
        ]);
    }

    public function tickets(Request $request, ReporteTicketsService $servicio): JsonResponse|BinaryFileResponse|Response
    {
        [$desde, $hasta] = $this->rango($request);
        $reporte = $servicio->generar($desde, $hasta);

        return $this->responder($request, $reporte, [
            'archivo'   => "mesa-de-ayuda-{$reporte['desde']}-a-{$reporte['hasta']}",
            'titulo'    => 'Mesa de ayuda por técnico y categoría',
            'subtitulo' => 'Del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y'),
            'vista'     => 'pdf.tic.tickets',
            'excel'     => fn () => ReportesTicExcel::tickets($reporte),
        ]);
    }

    public function satisfaccion(Request $request, ReporteSatisfaccionService $servicio): JsonResponse|BinaryFileResponse|Response
    {
        [$desde, $hasta] = $this->rango($request);
        $reporte = $servicio->generar($desde, $hasta);

        return $this->responder($request, $reporte, [
            'archivo'   => "satisfaccion-{$reporte['desde']}-a-{$reporte['hasta']}",
            'titulo'    => 'Satisfacción con la mesa de ayuda',
            'subtitulo' => 'Encuestas del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y'),
            'vista'     => 'pdf.tic.satisfaccion',
            'excel'     => fn () => ReportesTicExcel::satisfaccion($reporte),
        ]);
    }

    /** @return array<string, list<mixed>> */
    private function reglaFormato(): array
    {
        return ['formato' => ['nullable', Rule::in(['json', 'excel', 'pdf'])]];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function rango(Request $request): array
    {
        $datos = $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            ...$this->reglaFormato(),
        ], [], ['desde' => 'desde', 'hasta' => 'hasta']);

        $desde = Carbon::parse($datos['desde'])->startOfDay();
        $hasta = Carbon::parse($datos['hasta'])->endOfDay();
        if ($desde->diffInDays($hasta) > 366) {
            throw ValidationException::withMessages(['hasta' => 'El reporte cubre hasta un año.']);
        }

        return [$desde, $hasta];
    }

    /**
     * @param  array<string, mixed>  $reporte
     * @param  array{archivo: string, titulo: string, subtitulo: string, vista: string, excel: callable(): LibroReporteTic}  $salida
     */
    private function responder(Request $request, array $reporte, array $salida): JsonResponse|BinaryFileResponse|Response
    {
        return match ($request->input('formato')) {
            'excel' => Excel::download(($salida['excel'])(), "{$salida['archivo']}.xlsx"),
            'pdf'   => Pdf::loadView($salida['vista'], [
                'reporte'   => $reporte,
                'titulo'    => $salida['titulo'],
                'subtitulo' => $salida['subtitulo'],
                // Un reporte se arma con los datos de hoy: lleva el logo de hoy.
                ...app(\App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface::class)->paraDocumento(),
            ])->setPaper('a4', 'landscape')->download("{$salida['archivo']}.pdf"),
            default => ApiResponse::ok($reporte, $salida['titulo'] . '.'),
        };
    }
}
