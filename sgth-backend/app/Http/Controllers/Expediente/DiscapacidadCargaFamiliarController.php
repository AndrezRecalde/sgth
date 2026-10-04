<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\DiscapacidadCargaFamiliarRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\DiscapacidadCargaFamiliar;
use Illuminate\Http\JsonResponse;

/**
 * La discapacidad de una carga familiar. La marca `persona_con_discapacidad`
 * la mantiene CondicionCargaFamiliarObserver, no este controlador.
 */
class DiscapacidadCargaFamiliarController extends Controller
{
    public function store(DiscapacidadCargaFamiliarRequest $request, int $cargaId): JsonResponse
    {
        $carga = CargaFamiliar::findOrFail($cargaId);

        $discapacidad = $carga->discapacidades()->create($request->validated());

        return ApiResponse::created(
            $discapacidad,
            'Discapacidad registrada en la carga familiar.'
        );
    }

    public function update(
        DiscapacidadCargaFamiliarRequest $request,
        int $cargaId,
        int $id
    ): JsonResponse {
        $discapacidad = DiscapacidadCargaFamiliar::where('carga_familiar_id', $cargaId)
            ->findOrFail($id);

        $discapacidad->update($request->validated());

        return ApiResponse::ok($discapacidad, 'Discapacidad actualizada.');
    }

    public function destroy(int $cargaId, int $id): JsonResponse
    {
        DiscapacidadCargaFamiliar::where('carga_familiar_id', $cargaId)
            ->findOrFail($id)
            ->delete();

        return ApiResponse::ok(null, 'Discapacidad eliminada.');
    }
}
