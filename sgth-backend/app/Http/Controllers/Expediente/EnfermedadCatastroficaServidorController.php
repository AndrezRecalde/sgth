<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreEnfermedadCatastroficaRequest;
use App\Http\Requests\Expediente\UpdateEnfermedadCatastroficaRequest;
use App\Models\Expediente\EnfermedadCatastroficaServidor;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\EnfermedadCatastroficaServidorService;
use Illuminate\Http\JsonResponse;
use App\Http\Responses\ApiResponse;

/**
 * Las enfermedades catastróficas del servidor. Mismo ajuste que
 * DiscapacidadServidorController (2026-10-03): ApiResponse, y sin `show`.
 */
class EnfermedadCatastroficaServidorController extends Controller
{
    public function __construct(private EnfermedadCatastroficaServidorService $enfermedadService)
    {
    }

    public function index(int $servidorId): JsonResponse
    {
        $enfermedades = $this->enfermedadService->listar($servidorId);
        return ApiResponse::ok($enfermedades, 'Enfermedades catastróficas del servidor.');
    }

    public function store(StoreEnfermedadCatastroficaRequest $request, int $servidorId): JsonResponse
    {
        // Sin esto, un servidor inexistente reventaba la FK con un 500.
        Servidor::findOrFail($servidorId);

        $enfermedad = $this->enfermedadService->crear($servidorId, $request->validated());
        return ApiResponse::created($enfermedad, 'Enfermedad catastrófica registrada con éxito.');
    }

    public function update(UpdateEnfermedadCatastroficaRequest $request, int $servidorId, EnfermedadCatastroficaServidor $enfermedade): JsonResponse
    {
        if ($enfermedade->servidor_id !== (int) $servidorId) {
            abort(404, 'Enfermedad no encontrada para este servidor.');
        }

        $enfermedadActualizada = $this->enfermedadService->actualizar($enfermedade, $request->validated());

        return ApiResponse::ok($enfermedadActualizada, 'Enfermedad catastrófica actualizada con éxito.');
    }

    public function destroy(int $servidorId, EnfermedadCatastroficaServidor $enfermedade): JsonResponse
    {
        if ($enfermedade->servidor_id !== (int) $servidorId) {
            abort(404, 'Enfermedad no encontrada para este servidor.');
        }

        $this->enfermedadService->eliminar($enfermedade);

        return ApiResponse::ok(null, 'Enfermedad catastrófica eliminada con éxito.');
    }
}
