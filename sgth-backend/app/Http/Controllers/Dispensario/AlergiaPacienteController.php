<?php

namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\AlergiaPaciente;
use App\Models\Dispensario\HistoriaClinica;
use App\Http\Requests\Dispensario\StoreAlergiaPacienteRequest;
use Illuminate\Http\JsonResponse;

class AlergiaPacienteController extends Controller
{
    public function index(int $historiaId): JsonResponse
    {
        $alergias = AlergiaPaciente::where('historia_clinica_id', $historiaId)->get();
        return ApiResponse::ok($alergias);
    }

    public function store(StoreAlergiaPacienteRequest $request, int $historiaId): JsonResponse
    {
        $historia = HistoriaClinica::findOrFail($historiaId);
        
        $datos = array_merge($request->validated(), ['historia_clinica_id' => $historia->id]);
        $alergia = AlergiaPaciente::create($datos);

        return ApiResponse::created($alergia, 'Alergia registrada exitosamente.');
    }

    public function destroy(int $historiaId, int $alergiaId): JsonResponse
    {
        $alergia = AlergiaPaciente::where('historia_clinica_id', $historiaId)->findOrFail($alergiaId);
        $alergia->delete();

        return ApiResponse::ok([], 'Alergia eliminada exitosamente.');
    }
}
