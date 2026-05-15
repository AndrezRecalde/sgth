<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreEnfermedadCatastroficaRequest;
use App\Http\Requests\Expediente\UpdateEnfermedadCatastroficaRequest;
use App\Models\Expediente\EnfermedadCatastroficaServidor;
use App\Services\Expediente\EnfermedadCatastroficaServidorService;
use Illuminate\Http\JsonResponse;

class EnfermedadCatastroficaServidorController extends Controller
{
    public function __construct(private EnfermedadCatastroficaServidorService $enfermedadService)
    {
    }

    public function index(int $servidorId): JsonResponse
    {
        $enfermedades = $this->enfermedadService->listar($servidorId);
        return response()->json(['data' => $enfermedades]);
    }

    public function store(StoreEnfermedadCatastroficaRequest $request, int $servidorId): JsonResponse
    {
        $enfermedad = $this->enfermedadService->crear($servidorId, $request->validated());
        return response()->json([
            'message' => 'Enfermedad catastrófica registrada con éxito.',
            'data'    => $enfermedad
        ], 201);
    }

    public function show(int $servidorId, EnfermedadCatastroficaServidor $enfermedade): JsonResponse
    {
        if ($enfermedade->servidor_id !== (int) $servidorId) {
            abort(404, 'Enfermedad no encontrada para este servidor.');
        }
        
        return response()->json(['data' => $enfermedade]);
    }

    public function update(UpdateEnfermedadCatastroficaRequest $request, int $servidorId, EnfermedadCatastroficaServidor $enfermedade): JsonResponse
    {
        if ($enfermedade->servidor_id !== (int) $servidorId) {
            abort(404, 'Enfermedad no encontrada para este servidor.');
        }

        $enfermedadActualizada = $this->enfermedadService->actualizar($enfermedade, $request->validated());
        
        return response()->json([
            'message' => 'Enfermedad catastrófica actualizada con éxito.',
            'data'    => $enfermedadActualizada
        ]);
    }

    public function destroy(int $servidorId, EnfermedadCatastroficaServidor $enfermedade): JsonResponse
    {
        if ($enfermedade->servidor_id !== (int) $servidorId) {
            abort(404, 'Enfermedad no encontrada para este servidor.');
        }

        $this->enfermedadService->eliminar($enfermedade);
        
        return response()->json(['message' => 'Enfermedad catastrófica eliminada con éxito.']);
    }
}
