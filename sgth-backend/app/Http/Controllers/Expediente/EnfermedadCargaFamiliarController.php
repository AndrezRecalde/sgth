<?php

namespace App\Http\Controllers\Expediente;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\EnfermedadCatastroficaCargaFamiliar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnfermedadCargaFamiliarController extends Controller
{
    public function store(Request $request, int $cargaId): JsonResponse
    {
        $carga = CargaFamiliar::findOrFail($cargaId);

        $validated = $request->validate([
            'tipo_enfermedad'    => ['required', 'string', 'max:150'],
            'codigo_cie10'       => ['nullable', 'string', 'max:20'],
            'fecha_diagnostico'  => ['nullable', 'date'],
        ]);

        $enfermedad = $carga->enfermedadesCatastroficas()->create($validated);

        // Marcar carga familiar con enfermedad catastrófica
        $carga->update(['posee_enfermedad_catastrofica' => true]);

        return ApiResponse::created(
            $enfermedad,
            'Enfermedad catastrófica registrada en la carga familiar.'
        );
    }

    public function destroy(int $cargaId, int $id): JsonResponse
    {
        $enfermedad = EnfermedadCatastroficaCargaFamiliar::where(
            'carga_familiar_id', $cargaId
        )->findOrFail($id);

        $enfermedad->delete();

        // Si no quedan enfermedades, actualizar flag
        $carga = CargaFamiliar::findOrFail($cargaId);
        if ($carga->enfermedadesCatastroficas()->count() === 0) {
            $carga->update(['posee_enfermedad_catastrofica' => false]);
        }

        return ApiResponse::ok(null, 'Enfermedad catastrófica eliminada.');
    }
}
