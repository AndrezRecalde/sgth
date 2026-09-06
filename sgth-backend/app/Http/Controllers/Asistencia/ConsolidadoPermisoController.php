<?php
namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Asistencia\ConsolidadoPermisoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConsolidadoPermisoController extends Controller
{
    private const TIPO_ETIQUETAS = [
        'personal'   => 'Personal',
        'oficial'    => 'Oficial',
        'enfermedad' => 'Por Enfermedad',
        'calamidad'  => 'Calamidad Domestica',
    ];

    public function __construct(private ConsolidadoPermisoService $servicio) {}

    public function consolidado(Request $request): JsonResponse
    {
        [$inicio, $fin, $tipo] = $this->filtros($request);

        return ApiResponse::ok(
            array_merge($this->servicio->generar($inicio, $fin, $tipo), [
                'filtros' => [
                    'fecha_inicio' => $inicio,
                    'fecha_fin'    => $fin,
                    'tipo'         => $tipo,
                ],
            ]),
            'Consolidado de permisos'
        );
    }

    public function exportarExcel(Request $request): mixed
    {
        [$inicio, $fin, $tipo] = $this->filtros($request);

        $filas = $this->servicio->paraExcel(
            $this->servicio->generar($inicio, $fin, $tipo)['consolidado']
        );

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="consolidado_permisos_' .
                now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($filas) {
            $handle = fopen('php://output', 'w');

            // BOM para que Excel reconozca el UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            if (!empty($filas)) {
                fputcsv($handle, array_keys($filas[0]), ';');

                foreach ($filas as $fila) {
                    fputcsv($handle, $fila, ';');
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function exportarPdf(Request $request): mixed
    {
        [$inicio, $fin, $tipo] = $this->filtros($request);

        $datos = $this->servicio->generar($inicio, $fin, $tipo);

        $pdf = app('dompdf.wrapper')
            ->setPaper('letter', 'landscape')
            ->loadView('permisos.consolidado-pdf', [
                'consolidado' => $this->servicio->paraPdf($datos['consolidado']),
                'totales'     => $datos['totales'],
                'fechaInicio' => Carbon::parse($inicio)->format('d/m/Y'),
                'fechaFin'    => Carbon::parse($fin)->format('d/m/Y'),
                'tipo'        => self::TIPO_ETIQUETAS[$tipo] ?? $tipo,
            ]);

        return $pdf->download(
            "consolidado_permisos_{$tipo}_" . now()->format('Y-m-d') . '.pdf'
        );
    }

    /**
     * Los tres puntos de entrada piden y validan lo mismo.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function filtros(Request $request): array
    {
        $validado = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'tipo'         => 'nullable|string|in:personal,oficial,enfermedad,calamidad',
        ]);

        return [
            Carbon::parse($validado['fecha_inicio'])->toDateString(),
            Carbon::parse($validado['fecha_fin'])->toDateString(),
            $validado['tipo'] ?? 'personal',
        ];
    }
}
