<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\FijarFinDelPeriodoRequest;
use App\Http\Requests\Expediente\RegistrarSubrogacionPorLeyRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\ContratoServidor;
use App\Services\Expediente\AutoridadElectaService;
use Illuminate\Http\JsonResponse;

/**
 * Las autoridades electas en funciones y su período (diseño de Acciones de
 * Personal, 9.14; fase 4.1).
 */
class AutoridadElectaController extends Controller
{
    public function __construct(private AutoridadElectaService $autoridades)
    {
    }

    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->autoridades->listar(), 'Autoridades electas en funciones.');
    }

    /** Las que entran: su período por empezar y a quién suceden (2026-10-21). */
    public function entrantes(): JsonResponse
    {
        return ApiResponse::ok($this->autoridades->entrantes(), 'Autoridades electas que entran.');
    }

    /**
     * Registra la subrogación por ley del Viceprefecto (COOTAD Art. 51; fase
     * 4.1b): nace en borrador con su acto de autoridad, que sigue el trámite
     * de cualquier acción.
     */
    public function subrogacionPorLey(RegistrarSubrogacionPorLeyRequest $request): JsonResponse
    {
        $subrogacion = $this->autoridades->registrarSubrogacionPorLey($request->validated());

        return ApiResponse::created(
            ['subrogacion_id' => $subrogacion->id, 'movimiento_id' => $subrogacion->movimiento_personal_id],
            'Subrogación por ley preparada en borrador: suscríbala y regístrela en la bandeja.'
        );
    }

    /** Fija el fin del período de una autoridad que no lo tiene. */
    public function fijarFinDelPeriodo(FijarFinDelPeriodoRequest $request, ContratoServidor $contrato): JsonResponse
    {
        $this->authorize('actualizar', $contrato->servidor);

        $contrato = $this->autoridades->fijarFinDelPeriodo($contrato, $request->validated('fecha_fin'));

        return ApiResponse::ok(
            ['contrato_id' => $contrato->id, 'fecha_fin' => $contrato->fecha_fin?->toDateString()],
            'Fin del período fijado: ese día el vínculo se cierra solo.'
        );
    }
}
