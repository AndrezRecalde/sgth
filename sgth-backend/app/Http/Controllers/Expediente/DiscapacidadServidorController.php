<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreDiscapacidadServidorRequest;
use App\Http\Requests\Expediente\UpdateDiscapacidadServidorRequest;
use App\Models\Expediente\DiscapacidadServidor;
use App\Services\Expediente\DiscapacidadServidorService;
use Illuminate\Http\JsonResponse;

class DiscapacidadServidorController extends Controller
{
    public function __construct(private DiscapacidadServidorService $discapacidadService)
    {
    }

    public function index(int $servidorId): JsonResponse
    {
        $discapacidades = $this->discapacidadService->listar($servidorId);
        return response()->json(['data' => $discapacidades]);
    }

    public function store(StoreDiscapacidadServidorRequest $request, int $servidorId): JsonResponse
    {
        $discapacidad = $this->discapacidadService->crear($servidorId, $request->validated());
        return response()->json([
            'message' => 'Discapacidad registrada con éxito.',
            'data'    => $discapacidad
        ], 201);
    }

    public function show(int $servidorId, DiscapacidadServidor $discapacidade): JsonResponse
    {
        if ($discapacidade->servidor_id !== (int) $servidorId) {
            abort(404, 'Discapacidad no encontrada para este servidor.');
        }
        
        return response()->json(['data' => $discapacidade]);
    }

    public function update(UpdateDiscapacidadServidorRequest $request, int $servidorId, DiscapacidadServidor $discapacidade): JsonResponse
    {
        if ($discapacidade->servidor_id !== (int) $servidorId) {
            abort(404, 'Discapacidad no encontrada para este servidor.');
        }

        $discapacidadActualizada = $this->discapacidadService->actualizar($discapacidade, $request->validated());
        
        return response()->json([
            'message' => 'Discapacidad actualizada con éxito.',
            'data'    => $discapacidadActualizada
        ]);
    }

    public function destroy(int $servidorId, DiscapacidadServidor $discapacidade): JsonResponse
    {
        if ($discapacidade->servidor_id !== (int) $servidorId) {
            abort(404, 'Discapacidad no encontrada para este servidor.');
        }

        $this->discapacidadService->eliminar($discapacidade);
        
        return response()->json(['message' => 'Discapacidad eliminada con éxito.']);
    }
}
