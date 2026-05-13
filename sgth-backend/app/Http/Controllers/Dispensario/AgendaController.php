<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\AgendaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class AgendaController extends Controller
{
    public function __construct(
        private readonly AgendaServiceInterface $agendaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([], 'Listado de agenda');
    }

    public function store(Request $request): JsonResponse
    {
        $cita = $this->agendaService->agendarCita($request->all());
        return ApiResponse::created($cita, 'Cita agendada');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(['id' => $id], 'Detalle de cita');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return ApiResponse::ok([], 'Cita actualizada');
    }

    public function destroy(int $id): JsonResponse
    {
        return ApiResponse::ok([], 'Cita cancelada');
    }
}