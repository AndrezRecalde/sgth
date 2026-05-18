<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\AntecedentePaciente;
use App\Models\Dispensario\HistoriaClinica;
use App\Http\Requests\Dispensario\StoreAntecedentePacienteRequest;
use Illuminate\Http\JsonResponse;

class AntecedentePacienteController extends Controller
{
    public function index(int $historiaId): JsonResponse
    {
        $antecedentes = AntecedentePaciente::where('historia_clinica_id', $historiaId)->get();
        return ApiResponse::ok($antecedentes);
    }

    public function store(StoreAntecedentePacienteRequest $request, int $historiaId): JsonResponse
    {
        $historia = HistoriaClinica::findOrFail($historiaId);
        
        $datos = array_merge($request->validated(), ['historia_clinica_id' => $historia->id]);
        $antecedente = AntecedentePaciente::create($datos);

        return ApiResponse::created($antecedente, 'Antecedente registrado exitosamente.');
    }

    public function destroy(int $historiaId, int $antecedenteId): JsonResponse
    {
        $antecedente = AntecedentePaciente::where('historia_clinica_id', $historiaId)->findOrFail($antecedenteId);
        $antecedente->delete();

        return ApiResponse::ok([], 'Antecedente eliminado exitosamente.');
    }
}
