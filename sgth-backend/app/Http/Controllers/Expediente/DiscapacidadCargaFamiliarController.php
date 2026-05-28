<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\DiscapacidadCargaFamiliar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscapacidadCargaFamiliarController extends Controller
{
    public function store(Request $request, int $cargaId): JsonResponse
    {
        $carga = CargaFamiliar::findOrFail($cargaId);

        $validated = $request->validate([
            'tipo_discapacidad'     => ['required', 'string'],
            'porcentaje'            => ['required', 'numeric', 'min:0.01', 'max:100'],
            'numero_carnet_conadis' => ['nullable', 'string', 'max:50'],
        ]);

        $discapacidad = $carga->discapacidades()->create($validated);

        // Marcar carga familiar con discapacidad
        $carga->update(['persona_con_discapacidad' => true]);

        return ApiResponse::created(
            $discapacidad,
            'Discapacidad registrada en la carga familiar.'
        );
    }

    public function destroy(int $cargaId, int $id): JsonResponse
    {
        $discapacidad = DiscapacidadCargaFamiliar::where(
            'carga_familiar_id', $cargaId
        )->findOrFail($id);

        $discapacidad->delete();

        // Si no quedan discapacidades, actualizar flag
        $carga = CargaFamiliar::findOrFail($cargaId);
        if ($carga->discapacidades()->count() === 0) {
            $carga->update(['persona_con_discapacidad' => false]);
        }

        return ApiResponse::ok(null, 'Discapacidad eliminada.');
    }
}
