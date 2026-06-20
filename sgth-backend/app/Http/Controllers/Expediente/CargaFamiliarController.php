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

    public function toggleEstado(
        int $servidorId,
        int $id
    ): JsonResponse {
        $carga = CargaFamiliar::where('servidor_id', $servidorId)
            ->findOrFail($id);

        $carga->update(['estado' => !$carga->estado]);

        $mensaje = $carga->estado
            ? 'Carga familiar activada.'
            : 'Carga familiar desactivada.';

        return ApiResponse::ok($carga, $mensaje);
    }

    public function misCargas(\Illuminate\Http\Request $request): JsonResponse
    {
        $servidorId = $request->user()->servidor_id;

        if (!$servidorId) {
            return ApiResponse::error(
                'El usuario no tiene un servidor vinculado.',
                422
            );
        }

        $cargas = CargaFamiliar::where('servidor_id', $servidorId)
            ->with(['discapacidades', 'enfermedadesCatastroficas'])
            ->orderBy('apellidos')
            ->get();

        return ApiResponse::ok($cargas, 'Mis cargas familiares.');
    }

    public function storeMisCargas(
        StoreCargaFamiliarRequest $request
    ): JsonResponse {
        $servidorId = $request->user()->servidor_id;

        if (!$servidorId) {
            return ApiResponse::error(
                'El usuario no tiene un servidor vinculado.',
                422
            );
        }

        $carga = CargaFamiliar::create(
            array_merge(
                $request->validated(),
                ['servidor_id' => $servidorId]
            )
        );

        return ApiResponse::created(
            $carga, 'Carga familiar registrada.'
        );
    }

    public function updateMisCargas(
        StoreCargaFamiliarRequest $request,
        int $id
    ): JsonResponse {
        $servidorId = $request->user()->servidor_id;

        $carga = CargaFamiliar::where('servidor_id', $servidorId)
            ->findOrFail($id);

        $carga->update($request->validated());

        return ApiResponse::ok(
            $carga, 'Carga familiar actualizada.'
        );
    }

    public function destroyMisCargas(
        \Illuminate\Http\Request $request,
        int $id
    ): JsonResponse {
        $servidorId = $request->user()->servidor_id;

        $carga = CargaFamiliar::where('servidor_id', $servidorId)
            ->findOrFail($id);

        $carga->delete();

        return ApiResponse::ok(null, 'Carga familiar eliminada.');
    }
}