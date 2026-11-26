<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\FijarFinDelPeriodoRequest;
use App\Http\Requests\Expediente\RegistrarSubrogacionPorLeyRequest;
use App\Http\Requests\Expediente\RegistrarVacacionesDeAutoridadRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
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

    /** Las dignidades que existen y quién las ocupa, para el formulario (4.1). */
    public function dignidades(): JsonResponse
    {
        return ApiResponse::ok($this->autoridades->dignidades(), 'Dignidades de elección popular.');
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

    /**
     * Registra las vacaciones que el Consejo le concede a una autoridad electa
     * (COOTAD Art. 47 s; fase 4.1d). La resolución es la autorización y el
     * papel firmado a la vez, así que los días salen del saldo de una vez.
     */
    public function vacaciones(RegistrarVacacionesDeAutoridadRequest $request): JsonResponse
    {
        $servidor = Servidor::findOrFail($request->integer('servidor_id'));
        $this->authorize('actualizar', $servidor);

        $vacacion = $this->autoridades->registrarVacaciones(
            $servidor,
            $request->validated(),
            $request->user(),
        );

        return ApiResponse::created(
            ['vacacion_id' => $vacacion->id, 'folio' => $vacacion->folio, 'dias' => (float) $vacacion->dias_solicitados],
            "Vacaciones registradas: {$vacacion->dias_solicitados} días descontados de su saldo."
        );
    }

    /** Corrige el fin del período de la autoridad que entró sin su acto de inicio. */
    public function fijarFinDelPeriodo(FijarFinDelPeriodoRequest $request, ContratoServidor $contrato): JsonResponse
    {
        $this->authorize('actualizar', $contrato->servidor);

        $contrato = $this->autoridades->fijarFinDelPeriodo($contrato, $request->validated('fecha_fin'));

        return ApiResponse::ok(
            ['contrato_id' => $contrato->id, 'fecha_fin' => $contrato->fecha_fin?->toDateString()],
            'Fin del período corregido: ese día el vínculo se cierra solo.'
        );
    }
}
