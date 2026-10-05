<?php

namespace App\Http\Controllers\Seleccion;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\Onboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La inducción de quien se incorpora (decisión de TH, 2026-10-05). El
 * checklist se crea al confirmar la incorporación de un candidato externo
 * —uno interno ya trabaja aquí—, pero ninguna pantalla lo mostraba aunque el
 * mensaje de incorporación prometía «su proceso de inducción».
 */
final class OnboardingController extends Controller
{
    public function update(Request $request, int $id): JsonResponse
    {
        $onboarding = Onboarding::findOrFail($id);

        $datos = $request->validate([
            'documentacion_entregada' => ['sometimes', 'boolean'],
            'induccion_completada'    => ['sometimes', 'boolean'],
            'contrato_firmado'        => ['sometimes', 'boolean'],
            'observaciones'           => ['nullable', 'string', 'max:1000'],
        ]);

        $onboarding->update([
            ...$datos,
            'updated_by' => $request->user()->id,
        ]);

        return ApiResponse::ok($onboarding->fresh(), 'Inducción actualizada.');
    }
}
