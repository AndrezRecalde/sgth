<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\AlergiaPaciente;
use App\Models\Dispensario\HistoriaClinica;
use App\Http\Requests\Dispensario\StoreAlergiaPacienteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlergiaPacienteController extends Controller
{
    public function index(int $historiaId): JsonResponse
    {
        $alergias = AlergiaPaciente::where('historia_clinica_id', $historiaId)
            ->with('anuladoPor:id,nombre,apellido')
            ->get();

        return ApiResponse::ok($alergias);
    }

    public function store(
        StoreAlergiaPacienteRequest $request,
        int $historiaId
    ): JsonResponse {
        $historia = HistoriaClinica::findOrFail($historiaId);
        $datos    = array_merge(
            $request->validated(),
            ['historia_clinica_id' => $historia->id]
        );
        $alergia = AlergiaPaciente::create($datos);

        return ApiResponse::created($alergia, 'Alergia registrada exitosamente.');
    }

    public function anular(
        Request $request,
        int $historiaId,
        int $alergiaId
    ): JsonResponse {
        $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        $alergia = AlergiaPaciente::where('historia_clinica_id', $historiaId)
            ->whereNull('anulado_en')
            ->findOrFail($alergiaId);

        $alergia->update([
            'anulado_en'       => now(),
            'anulado_por'      => $request->user()->id,
            'motivo_anulacion' => $request->string('motivo_anulacion')->value(),
        ]);

        return ApiResponse::ok($alergia, 'Alergia anulada correctamente.');
    }
}
