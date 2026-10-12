<?php

namespace App\Http\Controllers\Expediente;

use App\Enums\PasoDesvinculacion;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\Desvinculacion;
use App\Models\Expediente\DesvinculacionPaso;
use App\Services\Expediente\DesvinculacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La desvinculación de quien sale (diseño, 9.9; fase 7.4a): la lista, sus
 * pasos, lo que Talento Humano decide de cada uno y la devolución de los
 * bienes.
 */
class DesvinculacionController extends Controller
{
    public function __construct(
        private DesvinculacionService $desvinculaciones,
    ) {
    }

    /** Las abiertas, por defecto; o las cerradas, las canceladas o todas. */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'estado'   => ['nullable', Rule::in([Desvinculacion::ABIERTA, Desvinculacion::CERRADA, Desvinculacion::CANCELADA, 'todas'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponse::ok($this->desvinculaciones->listar($filtros), 'Desvinculaciones.');
    }

    public function show(Desvinculacion $desvinculacion): JsonResponse
    {
        return ApiResponse::ok($this->desvinculaciones->detalle($desvinculacion), 'Desvinculación.');
    }

    /** Hecho, no aplica o, para deshacerlo, pendiente; con su nota. */
    public function marcar(Request $request, Desvinculacion $desvinculacion, string $paso): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['required', Rule::in([DesvinculacionPaso::HECHO, DesvinculacionPaso::NO_APLICA, DesvinculacionPaso::PENDIENTE])],
            'nota'   => ['nullable', 'string', 'max:2000'],
        ], [], ['estado' => 'estado', 'nota' => 'nota']);

        $paso = PasoDesvinculacion::tryFrom($paso) ?? abort(404);

        return ApiResponse::ok(
            $this->desvinculaciones->marcar($desvinculacion, $paso, $datos['estado'], $datos['nota'] ?? null, $request->user()),
            "Se actualizó «{$paso->etiqueta()}».",
        );
    }

    /** El Director confirma la liquidación de vacaciones como se calculó (fase 7.4b). */
    public function confirmarLiquidacion(Request $request, Desvinculacion $desvinculacion): JsonResponse
    {
        return ApiResponse::ok(
            $this->desvinculaciones->confirmarLiquidacion($desvinculacion, $request->user()),
            'Se confirmó la liquidación de vacaciones.',
        );
    }

    /** El Director registra la indemnización, dentro del tope del Mandato 2 (fase 7.4b). */
    public function registrarIndemnizacion(Request $request, Desvinculacion $desvinculacion): JsonResponse
    {
        $datos = $request->validate([
            'concepto'   => ['required', Rule::in(array_keys(DesvinculacionService::CONCEPTOS_DE_INDEMNIZACION))],
            'monto'      => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'resolucion' => ['required', 'string', 'min:3', 'max:150'],
        ], [], ['concepto' => 'concepto', 'monto' => 'monto', 'resolucion' => 'resolución']);

        return ApiResponse::ok(
            $this->desvinculaciones->registrarIndemnizacion($desvinculacion, $datos, $request->user()),
            'Se registró la indemnización: no reingresa mientras no la devuelva.',
        );
    }

    /** Devolvió la indemnización: puede reingresar (fase 7.4b). */
    public function registrarDevolucion(Request $request, Desvinculacion $desvinculacion): JsonResponse
    {
        $datos = $request->validate([
            'fecha'     => ['required', 'date', 'before_or_equal:today'],
            'documento' => ['required', 'string', 'min:3', 'max:150'],
        ], [], ['fecha' => 'fecha de la devolución', 'documento' => 'documento']);

        return ApiResponse::ok(
            $this->desvinculaciones->registrarDevolucion($desvinculacion, $datos['fecha'], $datos['documento'], $request->user()),
            'Se registró la devolución de la indemnización: puede reingresar.',
        );
    }

    /** La devolución de un bien a su cargo, con el acta de TI. */
    public function devolverBien(Request $request, Desvinculacion $desvinculacion, int $asignacion): JsonResponse
    {
        $datos = $request->validate([
            'fecha_devolucion' => ['required', 'date', 'before_or_equal:today'],
            'observaciones'    => ['nullable', 'string', 'max:1000'],
        ], [], ['fecha_devolucion' => 'fecha de devolución', 'observaciones' => 'observaciones']);

        return ApiResponse::ok(
            $this->desvinculaciones->devolverBien($desvinculacion, $asignacion, $datos['fecha_devolucion'], $datos['observaciones'] ?? null),
            'Devolución registrada.',
        );
    }
}
