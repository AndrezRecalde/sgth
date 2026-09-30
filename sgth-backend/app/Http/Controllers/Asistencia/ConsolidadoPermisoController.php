<?php
namespace App\Http\Controllers\Asistencia;

use App\Enums\TipoPermiso;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\ConsolidadoPermisoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ConsolidadoPermisoController extends Controller
{
    /**
     * Años que puede abarcar un consolidado.
     *
     * El rango solo se validaba como «dos fechas, la segunda no anterior a la
     * primera», así que de 1900 a 2100 pasaba y recorría la tabla entera. Un
     * consolidado se hace por mes o por año; cinco es holgado de sobra y corta
     * el barrido absurdo.
     */
    private const MAXIMO_ANIOS = 5;

    public function __construct(private ConsolidadoPermisoService $servicio) {}

    public function consolidado(Request $request): JsonResponse
    {
        ['inicio' => $inicio, 'fin' => $fin, 'tipo' => $tipo,
         'servidor' => $servidor, 'unidad' => $unidad] = $this->filtros($request);

        return ApiResponse::ok(
            array_merge($this->servicio->generar($inicio, $fin, $tipo, $servidor?->id, $unidad?->id), [
                'filtros' => [
                    'fecha_inicio' => $inicio,
                    'fecha_fin'    => $fin,
                    'tipo'         => $tipo,
                    'servidor_id'  => $servidor?->id,
                    'unidad_administrativa_id' => $unidad?->id,
                ],
            ]),
            'Consolidado de permisos'
        );
    }

    public function exportarExcel(Request $request): mixed
    {
        ['inicio' => $inicio, 'fin' => $fin, 'tipo' => $tipo,
         'servidor' => $servidor, 'unidad' => $unidad] = $this->filtros($request);

        $filas = $this->servicio->paraExcel(
            $this->servicio->generar($inicio, $fin, $tipo, $servidor?->id, $unidad?->id)['consolidado']
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
        ['inicio' => $inicio, 'fin' => $fin, 'tipo' => $tipo,
         'servidor' => $servidor, 'unidad' => $unidad] = $this->filtros($request);

        $datos = $this->servicio->generar($inicio, $fin, $tipo, $servidor?->id, $unidad?->id);

        $pdf = app('dompdf.wrapper')
            ->setPaper('letter', 'landscape')
            ->loadView('permisos.consolidado-pdf', [
                'consolidado' => $this->servicio->paraPdf($datos['consolidado']),
                'totales'     => $datos['totales'],
                'fechaInicio' => Carbon::parse($inicio)->format('d/m/Y'),
                'fechaFin'    => Carbon::parse($fin)->format('d/m/Y'),
                'tipo'        => TipoPermiso::from($tipo)->etiqueta(),
                // Un informe de una sola persona tiene que decir de quién es:
                // si no, son tres filas sueltas sin dueño.
                'servidor'    => $servidor
                    ? mb_strtoupper(trim("{$servidor->apellido} {$servidor->segundo_apellido} {$servidor->nombre} {$servidor->segundo_nombre}"), 'UTF-8')
                    : null,
                'unidad'      => $unidad?->nombre,
            ]);

        return $pdf->download(
            "consolidado_permisos_{$tipo}_" . now()->format('Y-m-d') . '.pdf'
        );
    }

    /**
     * Los tres puntos de entrada piden y validan lo mismo.
     *
     * Por clave y no por posición: con cuatro filtros, un `[$a, $b, $c, $d]`
     * en tres sitios es una invitación a cruzar dos de ellos sin que nada se
     * queje.
     *
     * @return array{inicio: string, fin: string, tipo: string, servidor: ?Servidor, unidad: ?UnidadAdministrativa}
     */
    private function filtros(Request $request): array
    {
        $validado = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after_or_equal:fecha_inicio',
            // Del enum y no a mano, como hace `StorePermisoServidorRequest`:
            // un tipo nuevo tendría que acordarse de aparecer aquí.
            'tipo'         => ['nullable', 'string', Rule::in(TipoPermiso::valores())],
            // Opcional: sin él, el informe es de toda la institución; con él,
            // de una sola persona.
            'servidor_id'  => ['nullable', 'integer', 'exists:servidores,id'],
            // Opcional: la unidad y todo lo que cuelga de ella.
            'unidad_administrativa_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
        ]);

        $inicio = Carbon::parse($validado['fecha_inicio']);
        $fin    = Carbon::parse($validado['fecha_fin']);

        if ($inicio->diffInYears($fin) >= self::MAXIMO_ANIOS) {
            throw ValidationException::withMessages([
                'fecha_fin' => sprintf(
                    'El consolidado abarca como máximo %d años; el rango pedido es mayor.',
                    self::MAXIMO_ANIOS
                ),
            ]);
        }

        $servidor = isset($validado['servidor_id'])
            ? Servidor::find($validado['servidor_id'])
            : null;

        // El servidor manda: elegida una persona, la unidad ya no puede
        // recortar nada util --solo dejar el informe en blanco si no es la
        // suya--, asi que se ignora. El frontend ademas deshabilita el campo.
        $unidad = $servidor === null && isset($validado['unidad_administrativa_id'])
            ? UnidadAdministrativa::find($validado['unidad_administrativa_id'])
            : null;

        return [
            'inicio'   => $inicio->toDateString(),
            'fin'      => $fin->toDateString(),
            'tipo'     => $validado['tipo'] ?? 'personal',
            'servidor' => $servidor,
            'unidad'   => $unidad,
        ];
    }
}
