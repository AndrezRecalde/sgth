<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreHistoriaClinicaRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HistoriaClinicaController extends Controller
{
    public function __construct(
        private readonly HistoriaClinicaServiceInterface $historiaService
    ) {}

    public function store(
        StoreHistoriaClinicaRequest $request
    ): JsonResponse {
        $historia = $this->historiaService->crearHistoria(
            $request->validated()
        );

        return ApiResponse::created(
            $historia, 'Historia clínica creada.'
        );
    }

    public function crearPorCedula(Request $request): JsonResponse
    {
        $request->validate([
            'cedula_paciente' => ['required', 'string', 'min:5', 'max:20'],
            'tipo_paciente'   => ['sometimes', 'in:servidor,familiar,candidato'],
        ]);

        $historia = $this->historiaService->buscarOCrearPorCedula(
            cedula:       $request->string('cedula_paciente')->value(),
            tipoPaciente: $request->input('tipo_paciente', 'candidato'),
            userId:       $request->user()->id,
        );

        return ApiResponse::ok(
            $historia, 'Historia clínica lista.'
        );
    }

    public function contextoConsulta(
        int $id,
        Request $request
    ): JsonResponse {
        $contexto = $this->historiaService->obtenerContextoConsulta(
            $id,
            $request->integer('agenda_medica_id') ?: null
        );

        return ApiResponse::ok($contexto);
    }
}