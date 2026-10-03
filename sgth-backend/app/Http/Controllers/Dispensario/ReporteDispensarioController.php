<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\CatalogoReportesDispensarioInterface;
use App\Exports\ReporteDispensarioExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\FiltrosReporteDispensarioRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Dispensario\Reportes\AlcanceReporte;
use App\Services\Dispensario\Reportes\FiltrosReporte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ReporteDispensarioController extends Controller
{
    /** Lo que se enseña en pantalla antes de descargar; el archivo lleva todo. */
    private const MAXIMO_EN_PANTALLA = 500;

    public function __construct(
        private readonly CatalogoReportesDispensarioInterface $catalogo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $alcance = $this->alcance($request);

        return ApiResponse::ok([
            'alcance'  => $alcance->perfil,
            'propio'   => $alcance->soloLoPropio(),
            'reportes' => $this->catalogo->disponibles($alcance),
            'opciones' => $this->catalogo->opciones($alcance),
        ]);
    }

    public function show(FiltrosReporteDispensarioRequest $request, string $clave): JsonResponse
    {
        $resultado = $this->catalogo->generar(
            $clave, FiltrosReporte::desde($request->validated()), $this->alcance($request)
        );

        return ApiResponse::ok([
            'columnas'  => $resultado['columnas'],
            'filas'     => array_slice($resultado['filas'], 0, self::MAXIMO_EN_PANTALLA),
            'total'     => count($resultado['filas']),
            'recortado' => count($resultado['filas']) > self::MAXIMO_EN_PANTALLA,
        ]);
    }

    public function excel(FiltrosReporteDispensarioRequest $request, string $clave): BinaryFileResponse
    {
        $alcance = $this->alcance($request);
        $filtros = FiltrosReporte::desde($request->validated());
        $reporte = $this->catalogo->reporte($clave, $alcance);
        $resultado = $this->catalogo->generar($clave, $filtros, $alcance);

        return Excel::download(
            new ReporteDispensarioExport(
                $reporte->titulo(),
                // Una foto de hoy no tiene período: decirlo evita que se lea
                // como el stock de esas fechas.
                $reporte->usaPeriodo() ? 'Período: ' . $filtros->periodo() : 'Existencias al ' . now()->format('d/m/Y'),
                $alcance->descripcion(),
                $request->user()->nombre_completo ?? $request->user()->usuario_ti,
                $resultado['columnas'],
                $resultado['filas'],
            ),
            $reporte->usaPeriodo()
                ? "{$clave}_{$filtros->desde->format('Ymd')}_{$filtros->hasta->format('Ymd')}.xlsx"
                : "{$clave}_" . now()->format('Ymd') . '.xlsx'
        );
    }

    private function alcance(Request $request): AlcanceReporte
    {
        // El `role:` de la ruta ya deja pasar solo a estos perfiles; esto
        // cubre a quien llegue sin ninguno por otro camino.
        return AlcanceReporte::paraUsuario($request->user())
            ?? abort(403, 'No tiene acceso a los reportes del Dispensario.');
    }
}
