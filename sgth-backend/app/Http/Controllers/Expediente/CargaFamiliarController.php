<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\StoreCargaFamiliarRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CargaFamiliarController extends Controller
{
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
        $datos = array_merge($request->validated(), ['servidor_id' => $servidorId]);

        // Un familiar borrado se recupera con su misma fila, no se crea otro.
        // Su historia clínica del Dispensario cuelga de ese id y se numera con
        // la cédula, que es única: una fila nueva se quedaría sin historia.
        $borrada = CargaFamiliar::onlyTrashed()
            ->where('cedula', $datos['cedula'])
            ->first();

        if ($borrada) {
            DB::transaction(function () use ($borrada, $datos) {
                $borrada->restore();
                $borrada->update([...$datos, 'estado' => true]);
            });
            return ApiResponse::created($borrada, 'Carga familiar registrada.');
        }

        $carga = CargaFamiliar::create($datos);
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