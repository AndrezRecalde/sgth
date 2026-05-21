<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreHistorialAcademicoRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;

class HistorialAcademicoController extends Controller
{
    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        $historial = $servidor->historialAcademico()
            ->orderByDesc('fecha_inicio')
            ->get();
        return ApiResponse::ok($historial, 'Historial académico del servidor.');
    }

    public function store(
        StoreHistorialAcademicoRequest $request,
        int $servidorId
    ): JsonResponse {
        Servidor::findOrFail($servidorId);
        $registro = HistorialAcademicoServidor::create(
            array_merge($request->validated(), ['servidor_id' => $servidorId])
        );
        return ApiResponse::created($registro, 'Registro académico agregado.');
    }

    public function update(
        StoreHistorialAcademicoRequest $request,
        int $servidorId,
        int $id
    ): JsonResponse {
        $registro = HistorialAcademicoServidor::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $registro->update($request->validated());
        return ApiResponse::ok($registro, 'Registro académico actualizado.');
    }

    public function destroy(int $servidorId, int $id): JsonResponse
    {
        $registro = HistorialAcademicoServidor::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $registro->delete();
        return ApiResponse::ok(null, 'Registro académico eliminado.');
    }
}