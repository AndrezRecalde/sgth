<?php
namespace App\Http\Controllers\Asistencia;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\Servidor;
use App\Services\Asistencia\PeriodoVacacionService;
use App\Services\Asistencia\TopeAcumulacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La autorización va por `VacacionPolicy`: consultar el resumen sigue la regla
 * de leer las vacaciones de ese servidor, y todo lo que genera o recalcula
 * exige `gestionar-vacaciones`. Antes cualquier usuario autenticado podía
 * disparar «Generar todos» o recalcular un año ya cerrado.
 */
class PeriodoVacacionController extends Controller
{
    /**
     * El año, siempre validado igual en los cuatro endpoints que lo reciben.
     *
     * `generar` y `generar-todos` no lo validaban: hacían `(int) input('anio')`,
     * así que `"hola"` pasaba como 0 y `-5` como -5. La operación masiva recorre
     * la plantilla entera, de modo que un año inventado abría un período del año
     * cero a cada servidor activo. El 2020-2035 solo existía en el campo del
     * frontend, que es exactamente donde una validación no sirve de nada.
     *
     * El margen es holgado a propósito: hay que poder corregir un período viejo
     * y abrir el del año que viene.
     *
     * Pública y no privada por el generador del contrato: Scramble lee las
     * reglas del `validate()` resolviendo la constante, y desde fuera de la
     * clase no puede. Con ella privada, el aviso de que no pudo evaluarlas
     * acababa escrito dentro de la descripción del endpoint en
     * `api.generated.ts`.
     */
    public const REGLA_ANIO = ['integer', 'min:2000', 'max:2100'];

    public function __construct(
        private PeriodoVacacionService $periodoService,
        private TopeAcumulacionService $tope,
    ) {}

    /**
     * Resumen de períodos y saldo de un servidor.
     */
    public function resumen(int $servidorId): JsonResponse
    {
        $this->authorize('verSaldo', [Vacacion::class, Servidor::findOrFail($servidorId)]);

        $resumen = $this->periodoService->resumen($servidorId);
        return ApiResponse::ok($resumen, 'Resumen de períodos de vacaciones.');
    }

    /**
     * Generar período del año actual para un servidor.
     */
    public function generar(Request $request, int $servidorId): JsonResponse
    {
        $this->authorize('gestionarPeriodos', Vacacion::class);

        $anio = $this->anioPedido($request);

        $servidor = Servidor::findOrFail($servidorId);
        $periodo  = $this->periodoService->generarPeriodo($servidor, $anio);

        return ApiResponse::ok($periodo, "Período {$anio} generado correctamente.");
    }

    /**
     * Qué cambiaría al forzar el recálculo de un período cerrado.
     *
     * No escribe nada: alimenta el diálogo de confirmación para que diga el
     * saldo concreto de antes y de después. La consecuencia tiene que verse
     * antes de aceptarla.
     */
    public function previsualizarRecalculo(Request $request, int $servidorId): JsonResponse
    {
        $this->authorize('gestionarPeriodos', Vacacion::class);

        $datos = $request->validate([
            'anio' => ['required', ...self::REGLA_ANIO],
        ]);

        $servidor = Servidor::findOrFail($servidorId);

        $previsualizacion = $this->periodoService->previsualizarRecalculo(
            $servidor, (int) $datos['anio']
        );

        if (! $previsualizacion) {
            return ApiResponse::error(
                "No existe un período {$datos['anio']} para este servidor.",
                null, 404
            );
        }

        return ApiResponse::ok($previsualizacion, 'Previsualización del recálculo.');
    }

    /**
     * Recalcula un período YA CERRADO, a sabiendas.
     *
     * Va por su propia ruta y no como una bandera de `generar`: alterar un
     * saldo certificado tiene que ser una decisión explícita sobre un servidor
     * y un año concretos, nunca el efecto colateral de una operación masiva.
     * El servicio lo registra en la bitácora con los valores de antes y después.
     */
    public function recalcularCerrado(Request $request, int $servidorId): JsonResponse
    {
        $this->authorize('gestionarPeriodos', Vacacion::class);

        $datos = $request->validate([
            'anio' => ['required', ...self::REGLA_ANIO],
        ]);

        $servidor = Servidor::findOrFail($servidorId);

        $periodo = PeriodoVacacion::where('servidor_id', $servidorId)
            ->where('anio', $datos['anio'])
            ->first();

        if (! $periodo) {
            return ApiResponse::error(
                "No existe un período {$datos['anio']} para este servidor.",
                null, 404
            );
        }

        if ($periodo->estado === 'abierto') {
            return ApiResponse::error(
                'Este período está abierto: se recalcula con la generación normal, sin forzar.',
                null, 422
            );
        }

        $saldoAnterior = (float) $periodo->dias_saldo;

        $actualizado = $this->periodoService->generarPeriodo(
            $servidor, (int) $datos['anio'], forzar: true
        );

        return ApiResponse::ok(
            $actualizado,
            sprintf(
                'Período %d recalculado. El saldo pasó de %.2f a %.2f días.',
                $datos['anio'], $saldoAnterior, (float) $actualizado->dias_saldo
            )
        );
    }

    /**
     * Generar períodos para todos los servidores (admin).
     *
     * Nunca fuerza: los períodos cerrados se devuelven intactos. Es una
     * operación de rutina y tiene que ser inofensiva.
     */
    public function generarTodos(Request $request): JsonResponse
    {
        $this->authorize('gestionarPeriodos', Vacacion::class);

        $anio      = $this->anioPedido($request);
        $generados = $this->periodoService->generarPeriodosAnuales($anio);

        return ApiResponse::ok(
            ['generados' => $generados],
            "Períodos {$anio} generados para {$generados} servidores."
        );
    }

    /**
     * Quién está cerca de su tope de acumulación o lo pasa.
     *
     * Solo lee: lo ve quien ve las vacaciones de toda la institución. Vencer
     * el excedente es otra ruta y otro permiso.
     */
    public function excedentes(Request $request): JsonResponse
    {
        $this->authorize('verTodas', Vacacion::class);

        $filas = $this->tope->enSeguimiento($request->boolean('solo_excedidos'));

        return ApiResponse::ok($filas, 'Servidores cerca o por encima de su tope de acumulación.');
    }

    /**
     * Vence el excedente de un servidor sobre su tope. Queda en la bitácora.
     */
    public function vencerExcedente(Request $request, int $servidorId): JsonResponse
    {
        $this->authorize('gestionarPeriodos', Vacacion::class);

        $resultado = $this->tope->vencerExcedente(
            Servidor::findOrFail($servidorId), $request->user()
        );

        return ApiResponse::ok($resultado, sprintf(
            'Vencieron %s días. El saldo pasó de %s a %s días (tope: %s).',
            number_format($resultado['dias_vencidos'], 2),
            number_format($resultado['saldo_antes'], 2),
            number_format($resultado['saldo_despues'], 2),
            number_format($resultado['tope'], 2)
        ));
    }

    /**
     * El año que se pide generar, o el corriente si no viene ninguno.
     *
     * Se valida aunque sea opcional: lo que llega mal se rechaza con su mensaje
     * en vez de convertirse en un cero silencioso.
     */
    private function anioPedido(Request $request): int
    {
        $datos = $request->validate([
            'anio' => ['nullable', ...self::REGLA_ANIO],
        ]);

        return (int) ($datos['anio'] ?? now()->year);
    }
}
