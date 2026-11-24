<?php
namespace App\Http\Controllers\Asistencia;

use App\Enums\TipoPermiso;
use App\Exports\ConsolidadoPermisosExport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\ConsolidadoPermisoService;
use App\Support\NombreDelServidor;
use App\Support\PeriodoEnLetras;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

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

    /**
     * El consolidado en Excel. Era un CSV sin título ni período y con la
     * cédula expuesta a perder el cero; ver `ConsolidadoPermisosExport`.
     */
    public function exportarExcel(Request $request): mixed
    {
        $doc = $this->documento($request);

        return Excel::download(
            new ConsolidadoPermisosExport(
                titulo: $doc['titulo'],
                subtitulo: "{$doc['periodo']} · {$doc['alcance']} · Generado el " . now()->format('d/m/Y H:i'),
                rotuloCuenta: $doc['rotuloCuenta'],
                hoja: $doc['encabezado']['hoja'],
                filas: $doc['consolidado']->all(),
            ),
            "{$doc['archivo']}.xlsx",
        );
    }

    /**
     * El consolidado en PDF, en el estilo de los documentos institucionales
     * (2026-11-24): A4 apaisado como los demás listados, membrete y pie fijos
     * en cada hoja y los títulos de columna repetidos si pasa a otra.
     */
    public function exportarPdf(Request $request): mixed
    {
        $doc = $this->documento($request);

        // Con subconjunto de fuentes: con DejaVu Sans entera el PDF pasaba de
        // ~110 KB a ~1 MB.
        $pdf = app('dompdf.wrapper')
            ->setOption('enable_font_subsetting', true)
            ->setPaper('a4', 'landscape')
            ->loadView('permisos.consolidado-pdf', [
                ...$doc,
                // Un informe se arma con los datos de hoy: lleva el logo de hoy.
                ...app(\App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface::class)->paraDocumento(),
            ]);

        return $pdf->download("{$doc['archivo']}.pdf");
    }

    /**
     * El encabezado del informe. Ningún encabezado dice «Asistencia» (pedido
     * de Talento Humano, 2026-11-24): el de Asistencia va sin supra y sale a
     * nombre de la Dirección de Talento Humano. `AusentismoSsoController` lo
     * cambia por el suyo.
     *
     * @return array{supra: ?string, titulo: string, dependencia: string, conTipo: bool, archivo: string, hoja: string}
     */
    protected function encabezado(): array
    {
        return [
            'supra'       => null,
            'titulo'      => 'Consolidado de permisos',
            'dependencia' => 'Dirección de Talento Humano',
            'conTipo'     => true,
            'archivo'     => 'consolidado_permisos',
            'hoja'        => 'Consolidado',
        ];
    }

    /**
     * Lo que comparten el PDF y el Excel: las filas, los totales y cómo se
     * nombran el período, el alcance y la cuenta.
     *
     * @return array<string, mixed>
     */
    private function documento(Request $request): array
    {
        ['inicio' => $inicio, 'fin' => $fin, 'tipo' => $tipo,
         'servidor' => $servidor, 'unidad' => $unidad] = $this->filtros($request);

        $datos = $this->servicio->generar($inicio, $fin, $tipo, $servidor?->id, $unidad?->id);
        $encabezado = $this->encabezado();
        $etiquetaTipo = TipoPermiso::from($tipo)->etiqueta();
        $esEnfermedad = $tipo === TipoPermiso::ENFERMEDAD->value;

        // Un informe de una sola persona tiene que decir de quién es: si no,
        // son tres filas sueltas sin dueño. La unidad incluye las que cuelgan
        // de ella, así que también se dice.
        $alcance = match (true) {
            $servidor !== null => 'Servidor: ' . mb_strtoupper(NombreDelServidor::de($servidor), 'UTF-8'),
            $unidad !== null   => "Unidad: {$unidad->nombre}, con las que dependen de ella",
            default            => 'Toda la institución',
        };

        return [
            'encabezado'   => $encabezado,
            'titulo'       => $encabezado['conTipo'] ? "{$encabezado['titulo']} — {$etiquetaTipo}" : $encabezado['titulo'],
            'subtipo'      => $encabezado['conTipo'] ? $etiquetaTipo : null,
            'periodo'      => ucfirst(PeriodoEnLetras::de(Carbon::parse($inicio), Carbon::parse($fin))),
            'alcance'      => $alcance,
            // En enfermedad entran también los reposos médicos aprobados:
            // «permisos» se queda corto.
            'rotuloCuenta' => $esEnfermedad ? 'Ausencias' : 'Permisos',
            'esEnfermedad' => $esEnfermedad,
            'consolidado'  => $this->servicio->paraPdf($datos['consolidado']),
            'totales'      => $datos['totales'],
            'archivo'      => "{$encabezado['archivo']}_{$tipo}_{$inicio}_{$fin}",
        ];
    }

    /**
     * Los tres puntos de entrada piden y validan lo mismo.
     *
     * Por clave y no por posición: con cuatro filtros, un `[$a, $b, $c, $d]`
     * en tres sitios es una invitación a cruzar dos de ellos sin que nada se
     * queje.
     *
     * Protegido y no privado: el ausentismo de SSO es este mismo informe con
     * el tipo fijo (`AusentismoSsoController`).
     *
     * @return array{inicio: string, fin: string, tipo: string, servidor: ?Servidor, unidad: ?UnidadAdministrativa}
     */
    protected function filtros(Request $request): array
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
