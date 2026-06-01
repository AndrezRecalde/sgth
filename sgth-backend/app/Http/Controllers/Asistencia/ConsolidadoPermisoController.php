<?php
namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Expediente\Servidor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ConsolidadoPermisoController extends Controller
{
    public function consolidado(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'tipo'         => 'nullable|string',
        ]);

        $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
        $fechaFin    = Carbon::parse($request->fecha_fin)->endOfDay();
        $tipo        = $request->tipo ?? 'personal';

        // Obtener permisos agrupados por servidor
        $permisos = PermisoServidor::with([
                'servidor.unidadAdministrativa',
            ])
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('tipo', $tipo)
            ->whereNotIn('estado', ['anulado', 'pendiente'])
            ->get();

        // Agrupar por servidor
        $consolidado = $permisos
            ->groupBy('servidor_id')
            ->map(function ($items) {
                $servidor = $items->first()->servidor;

                $totalMinutos = $items->sum(function ($p) {
                    $inicio = Carbon::parse($p->hora_inicio);
                    $fin    = Carbon::parse($p->hora_fin);
                    return $inicio->diffInMinutes($fin);
                });

                $totalHoras = intdiv($totalMinutos, 60);
                $minRest    = $totalMinutos % 60;
                $totalDias  = round($totalMinutos / 480, 2);

                return [
                    'servidor_id'      => $servidor?->id,
                    'servidor_nombre'  => mb_strtoupper(implode(' ', array_filter([
                        $servidor?->apellido,
                        $servidor?->segundo_apellido,
                        $servidor?->nombre,
                        $servidor?->segundo_nombre,
                    ])), 'UTF-8'),
                    'cedula'           => $servidor?->cedula,
                    'unidad'           => $servidor?->unidadAdministrativa?->nombre ?? '—',
                    'total_permisos'   => $items->count(),
                    'total_minutos'    => $totalMinutos,
                    'tiempo_total'     => sprintf('%02d:%02d', $totalHoras, $minRest),
                    'total_dias'       => $totalDias,
                ];
            })
            ->values();

        // Totales generales
        $totales = [
            'total_permisos'  => $consolidado->sum('total_permisos'),
            'total_minutos'   => $consolidado->sum('total_minutos'),
            'total_dias'      => round($consolidado->sum('total_dias'), 2),
        ];

        return ApiResponse::ok([
            'consolidado' => $consolidado,
            'totales'     => $totales,
            'filtros'     => [
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin'    => $request->fecha_fin,
                'tipo'         => $tipo,
            ],
        ], 'Consolidado de permisos');
    }

    public function exportarExcel(Request $request): mixed
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'tipo'         => 'nullable|string',
        ]);

        $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
        $fechaFin    = Carbon::parse($request->fecha_fin)->endOfDay();
        $tipo        = $request->tipo ?? 'personal';

        $permisos = PermisoServidor::with([
                'servidor.unidadAdministrativa',
            ])
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('tipo', $tipo)
            ->whereNotIn('estado', ['anulado', 'pendiente'])
            ->get();

        $consolidado = $permisos
            ->groupBy('servidor_id')
            ->map(function ($items) {
                $servidor     = $items->first()->servidor;
                $totalMinutos = $items->sum(function ($p) {
                    $inicio = Carbon::parse($p->hora_inicio);
                    $fin    = Carbon::parse($p->hora_fin);
                    return $inicio->diffInMinutes($fin);
                });
                $totalHoras = intdiv($totalMinutos, 60);
                $minRest    = $totalMinutos % 60;

                return [
                    'Cedula'          => $servidor?->cedula,
                    'Servidor'        => mb_strtoupper(implode(' ', array_filter([
                        $servidor?->apellido,
                        $servidor?->segundo_apellido,
                        $servidor?->nombre,
                        $servidor?->segundo_nombre,
                    ])), 'UTF-8'),
                    'Unidad'          => $servidor?->unidadAdministrativa?->nombre ?? '—',
                    'Total Permisos'  => $items->count(),
                    'Total Minutos'   => $totalMinutos,
                    'Tiempo Total'    => sprintf('%02d:%02d', $totalHoras, $minRest),
                    'Total Dias'      => round($totalMinutos / 480, 2),
                ];
            })
            ->values()
            ->toArray();

        // Generar CSV (compatible sin librerías adicionales)
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="consolidado_permisos_' .
                now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($consolidado) {
            $handle = fopen('php://output', 'w');
            // BOM para Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            if (!empty($consolidado)) {
                fputcsv($handle, array_keys($consolidado[0]), ';');
                foreach ($consolidado as $row) {
                    fputcsv($handle, $row, ';');
                }
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportarPdf(Request $request): mixed
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            'tipo'         => 'nullable|string',
        ]);

        $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
        $fechaFin    = Carbon::parse($request->fecha_fin)->endOfDay();
        $tipo        = $request->tipo ?? 'personal';

        $permisos = PermisoServidor::with([
                'servidor.unidadAdministrativa',
            ])
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->where('tipo', $tipo)
            ->whereNotIn('estado', ['anulado', 'pendiente'])
            ->get();

        $consolidado = $permisos
            ->groupBy('servidor_id')
            ->map(function ($items) {
                $servidor     = $items->first()->servidor;
                $totalMinutos = $items->sum(function ($p) {
                    $inicio = Carbon::parse($p->hora_inicio);
                    $fin    = Carbon::parse($p->hora_fin);
                    return $inicio->diffInMinutes($fin);
                });
                $totalHoras = intdiv($totalMinutos, 60);
                $minRest    = $totalMinutos % 60;

                return [
                    'cedula'         => $servidor?->cedula,
                    'nombre'         => mb_strtoupper(implode(' ', array_filter([
                        $servidor?->apellido,
                        $servidor?->segundo_apellido,
                        $servidor?->nombre,
                        $servidor?->segundo_nombre,
                    ])), 'UTF-8'),
                    'unidad'         => $servidor?->unidadAdministrativa?->nombre ?? '—',
                    'total_permisos' => $items->count(),
                    'total_minutos'  => $totalMinutos,
                    'tiempo_total'   => sprintf('%02d:%02d', $totalHoras, $minRest),
                    'total_dias'     => round($totalMinutos / 480, 2),
                ];
            })
            ->values();

        $totales = [
            'total_permisos' => $consolidado->sum('total_permisos'),
            'total_minutos'  => $consolidado->sum('total_minutos'),
            'total_dias'     => round($consolidado->sum('total_dias'), 2),
        ];

        $tipoLabels = [
            'personal'   => 'Personal',
            'oficial'    => 'Oficial',
            'enfermedad' => 'Por Enfermedad',
            'calamidad'  => 'Calamidad Domestica',
        ];

        $pdf = app('dompdf.wrapper')
            ->setPaper('letter', 'landscape')
            ->loadView('permisos.consolidado-pdf', [
                'consolidado'  => $consolidado,
                'totales'      => $totales,
                'fechaInicio'  => $fechaInicio->format('d/m/Y'),
                'fechaFin'     => $fechaFin->format('d/m/Y'),
                'tipo'         => $tipoLabels[$tipo] ?? $tipo,
            ]);

        return $pdf->download(
            "consolidado_permisos_{$tipo}_" .
            now()->format('Y-m-d') . '.pdf'
        );
    }
}
