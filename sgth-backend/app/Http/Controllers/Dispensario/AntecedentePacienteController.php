<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\AntecedentePaciente;
use App\Models\Dispensario\HistoriaClinica;
use App\Http\Requests\Dispensario\StoreAntecedentePacienteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AntecedentePacienteController extends Controller
{
    public function index(int $historiaId): JsonResponse
    {
        $antecedentes = AntecedentePaciente::where('historia_clinica_id', $historiaId)
            ->with('anuladoPor:id,nombre,apellido')
            ->get();

        return ApiResponse::ok($antecedentes);
    }

    public function store(
        StoreAntecedentePacienteRequest $request,
        int $historiaId
    ): JsonResponse {
        $historia = HistoriaClinica::findOrFail($historiaId);
        $datos    = array_merge(
            $request->validated(),
            ['historia_clinica_id' => $historia->id]
        );
        $antecedente = AntecedentePaciente::create($datos);

        return ApiResponse::created($antecedente, 'Antecedente registrado exitosamente.');
    }

    public function anular(
        Request $request,
        int $historiaId,
        int $antecedenteId
    ): JsonResponse {
        $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        $antecedente = AntecedentePaciente::where('historia_clinica_id', $historiaId)
            ->whereNull('anulado_en')
            ->findOrFail($antecedenteId);

        $antecedente->update([
            'anulado_en'       => now(),
            'anulado_por'      => $request->user()->id,
            'motivo_anulacion' => $request->string('motivo_anulacion')->value(),
        ]);

        return ApiResponse::ok($antecedente, 'Antecedente anulado correctamente.');
    }
}
