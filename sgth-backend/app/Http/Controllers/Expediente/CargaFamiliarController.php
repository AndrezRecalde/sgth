<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreCargaFamiliarRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;

class CargaFamiliarController extends Controller
{
    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        $cargas = $servidor->cargasFamiliares()
            ->with(['discapacidades', 'enfermedadesCatastroficas'])
            ->orderBy('apellidos')
            ->get();
        return ApiResponse::ok($cargas, 'Cargas familiares del servidor.');
    }

    public function store(
        StoreCargaFamiliarRequest $request,
        int $servidorId
    ): JsonResponse {
        Servidor::findOrFail($servidorId);
        $carga = CargaFamiliar::create(
            array_merge($request->validated(), ['servidor_id' => $servidorId])
        );
        return ApiResponse::created($carga, 'Carga familiar registrada.');
    }

    public function update(
        StoreCargaFamiliarRequest $request,
        int $servidorId,
        int $id
    ): JsonResponse {
        $carga = CargaFamiliar::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $carga->update($request->validated());
        return ApiResponse::ok($carga, 'Carga familiar actualizada.');
    }

    public function destroy(int $servidorId, int $id): JsonResponse
    {
        $carga = CargaFamiliar::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $carga->delete();
        return ApiResponse::ok(null, 'Carga familiar eliminada.');
    }
}