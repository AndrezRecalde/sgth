<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreCargaFamiliarRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Services\Expediente\CargaFamiliarService;
use Illuminate\Http\JsonResponse;

class CargaFamiliarController extends Controller
{
    public function __construct(private readonly CargaFamiliarService $cargas) {}

    public function index(int $servidorId): JsonResponse
    {
        $servidor = Servidor::findOrFail($servidorId);
        $cargas = $servidor->cargasFamiliares()
            ->with(['discapacidades', 'enfermedadesCatastroficas'])
            // Los hermanos comparten apellidos: sin desempate cambiaban de
            // orden entre una recarga y otra.
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->orderBy('id')
            ->get();
        return ApiResponse::ok($cargas, 'Cargas familiares del servidor.');
    }

    public function store(
        StoreCargaFamiliarRequest $request,
        int $servidorId
    ): JsonResponse {
        Servidor::findOrFail($servidorId);

        // Un familiar borrado se recupera con su misma fila (ver el servicio).
        $carga = $this->cargas->registrar($servidorId, $request->validated());

        return ApiResponse::created($carga, 'Carga familiar registrada.');
    }

    public function update(
        StoreCargaFamiliarRequest $request,
        int $servidorId,
        int $id
    ): JsonResponse {
        $carga = CargaFamiliar::where('servidor_id', $servidorId)
            ->findOrFail($id);
        $this->cargas->actualizar($carga, $request->validated());
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

        $this->cargas->alternarEstado($carga);

        $mensaje = $carga->estado
            ? 'Carga familiar activada.'
            : 'Carga familiar desactivada.';

        return ApiResponse::ok($carga, $mensaje);
    }
}
