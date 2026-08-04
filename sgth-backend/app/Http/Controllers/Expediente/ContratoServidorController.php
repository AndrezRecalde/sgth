<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\CerrarContratoServidorRequest;
use App\Http\Requests\Expediente\ReprogramarPlazoContratoRequest;
use App\Http\Requests\Expediente\StoreContratoServidorRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\ContratoServidor;
use App\Services\Expediente\ContratoServidorService;
use Illuminate\Http\JsonResponse;

class ContratoServidorController extends Controller
{
    public function __construct(private ContratoServidorService $contratoService)
    {
    }

    public function index(int $servidorId): JsonResponse
    {
        $contratos = $this->contratoService->listar($servidorId);
        return ApiResponse::ok($contratos, 'Contratos del servidor.');
    }

    /**
     * Actividad laboral: cada vínculo con las acciones de personal ocurridas
     * sobre él y la situación en que está hoy el servidor.
     */
    public function actividadLaboral(int $servidorId): JsonResponse
    {
        return ApiResponse::ok(
            $this->contratoService->actividadLaboral($servidorId),
            'Actividad laboral del servidor.'
        );
    }

    public function store(
        StoreContratoServidorRequest $request,
        int $servidorId
    ): JsonResponse {
        $contrato = $this->contratoService->crear(
            $servidorId, $request->validated()
        );
        return ApiResponse::created($contrato, 'Contrato registrado con éxito.');
    }

    public function show(int $servidorId, ContratoServidor $contrato): JsonResponse
    {
        if ($contrato->servidor_id !== (int) $servidorId) {
            abort(404, 'Contrato no encontrado para este servidor.');
        }
        
        $contrato->load(['unidadAdministrativa', 'puesto']);
        return response()->json(['data' => $contrato]);
    }

    /**
     * Cierra un contrato vigente (fecha_fin + motivo_fin). Un contrato
     * nunca se edita para cambiar de modalidad: se cierra este y se crea
     * uno nuevo con crear().
     */
    public function cerrar(
        CerrarContratoServidorRequest $request,
        int $servidorId,
        ContratoServidor $contrato
    ): JsonResponse {
        if ($contrato->servidor_id !== (int) $servidorId) {
            abort(404, 'Contrato no encontrado para este servidor.');
        }
        $contratoCerrado = $this->contratoService->cerrar(
            $contrato, $request->validated()
        );
        return ApiResponse::ok(
            $contratoCerrado, 'Contrato cerrado con éxito.'
        );
    }

    /**
     * Reprograma el plazo de un contrato vigente: prórroga o corrección de una
     * fecha mal digitada. Es lo único editable de un vínculo ya creado.
     */
    public function reprogramarPlazo(
        ReprogramarPlazoContratoRequest $request,
        int $servidorId,
        ContratoServidor $contrato
    ): JsonResponse {
        if ($contrato->servidor_id !== (int) $servidorId) {
            abort(404, 'Contrato no encontrado para este servidor.');
        }

        $actualizado = $this->contratoService->reprogramarPlazo(
            $contrato, $request->validated()
        );

        return ApiResponse::ok($actualizado, 'Plazo del contrato reprogramado.');
    }
}
