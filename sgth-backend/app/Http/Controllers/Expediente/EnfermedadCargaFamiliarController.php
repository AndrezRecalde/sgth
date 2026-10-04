<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expediente\EnfermedadCargaFamiliarRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\EnfermedadCatastroficaCargaFamiliar;
use Illuminate\Http\JsonResponse;

/**
 * La enfermedad catastrófica de una carga familiar. La marca
 * `posee_enfermedad_catastrofica` la mantiene CondicionCargaFamiliarObserver.
 */
class EnfermedadCargaFamiliarController extends Controller
{
    public function store(EnfermedadCargaFamiliarRequest $request, int $cargaId): JsonResponse
    {
        $carga = CargaFamiliar::findOrFail($cargaId);

        $enfermedad = $carga->enfermedadesCatastroficas()->create($request->validated());

        return ApiResponse::created(
            $enfermedad,
            'Enfermedad catastrófica registrada en la carga familiar.'
        );
    }

    public function update(
        EnfermedadCargaFamiliarRequest $request,
        int $cargaId,
        int $id
    ): JsonResponse {
        $enfermedad = EnfermedadCatastroficaCargaFamiliar::where('carga_familiar_id', $cargaId)
            ->findOrFail($id);

        $enfermedad->update($request->validated());

        return ApiResponse::ok($enfermedad, 'Enfermedad catastrófica actualizada.');
    }

    public function destroy(int $cargaId, int $id): JsonResponse
    {
        EnfermedadCatastroficaCargaFamiliar::where('carga_familiar_id', $cargaId)
            ->findOrFail($id)
            ->delete();

        return ApiResponse::ok(null, 'Enfermedad catastrófica eliminada.');
    }
}
