<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\AgendaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreAgendaMedicaRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AgendaController extends Controller
{
    public function __construct(
        private readonly AgendaServiceInterface $agendaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $agenda = $this->agendaService->listar($request->all());

        return ApiResponse::ok($agenda, 'Listado de agenda.');
    }

    public function store(
        StoreAgendaMedicaRequest $request
    ): JsonResponse {
        $cita = $this->agendaService->agendarCita(
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created($cita, 'Cita agendada.');
    }

    public function show(int $id): JsonResponse
    {
        $cita = $this->agendaService->obtener($id);

        return ApiResponse::ok($cita);
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $cita = $this->agendaService->actualizar(
            $id, $request->all()
        );

        return ApiResponse::ok($cita, 'Cita actualizada.');
    }

    public function destroy(int $id): JsonResponse
    {
        $cita = $this->agendaService->cancelar($id);

        return ApiResponse::ok($cita, 'Cita cancelada.');
    }

    public function listosParaConsulta(Request $request): JsonResponse
    {
        $turnos = $this->agendaService->listosParaConsulta(
            $request->user()->id
        );

        return ApiResponse::ok($turnos);
    }

    public function turnosDelDia(Request $request): JsonResponse
    {
        $turnos = $this->agendaService->turnosDelDia(
            $request->user()->id
        );

        return ApiResponse::ok($turnos);
    }

    public function noPresentado(
        Request $request,
        int $agenda
    ): JsonResponse {
        $turno = $this->agendaService->marcarNoPresentado(
            $agenda, $request->user()->id
        );

        return ApiResponse::ok($turno, 'Turno marcado como no presentado.');
    }

    public function reactivar(
        Request $request,
        int $agenda
    ): JsonResponse {
        $turno = $this->agendaService->reactivar(
            $agenda, $request->user()->id
        );

        return ApiResponse::ok($turno, 'Turno reactivado correctamente.');
    }
}