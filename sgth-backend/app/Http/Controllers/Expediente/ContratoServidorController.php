<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreContratoServidorRequest;
use App\Http\Requests\Expediente\UpdateContratoServidorRequest;
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
        return response()->json(['data' => $contratos]);
    }

    public function store(StoreContratoServidorRequest $request, int $servidorId): JsonResponse
    {
        $contrato = $this->contratoService->crear($servidorId, $request->validated());
        return response()->json([
            'message' => 'Contrato registrado con éxito.',
            'data'    => $contrato
        ], 201);
    }

    public function show(int $servidorId, ContratoServidor $contrato): JsonResponse
    {
        if ($contrato->servidor_id !== (int) $servidorId) {
            abort(404, 'Contrato no encontrado para este servidor.');
        }
        
        $contrato->load(['unidadAdministrativa', 'puesto']);
        return response()->json(['data' => $contrato]);
    }

    public function update(UpdateContratoServidorRequest $request, int $servidorId, ContratoServidor $contrato): JsonResponse
    {
        if ($contrato->servidor_id !== (int) $servidorId) {
            abort(404, 'Contrato no encontrado para este servidor.');
        }

        $contratoActualizado = $this->contratoService->actualizar($contrato, $request->validated());
        
        return response()->json([
            'message' => 'Contrato actualizado con éxito.',
            'data'    => $contratoActualizado
        ]);
    }

    public function destroy(int $servidorId, ContratoServidor $contrato): JsonResponse
    {
        if ($contrato->servidor_id !== (int) $servidorId) {
            abort(404, 'Contrato no encontrado para este servidor.');
        }

        $this->contratoService->eliminar($contrato);
        
        return response()->json(['message' => 'Contrato eliminado con éxito.']);
    }
}
