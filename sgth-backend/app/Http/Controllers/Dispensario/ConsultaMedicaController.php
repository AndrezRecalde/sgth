<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class ConsultaMedicaController extends Controller
{
    public function __construct(
        private readonly HistoriaClinicaServiceInterface $historiaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([], 'Listado de consultas');
    }

    public function store(Request $request): JsonResponse
    {
        $consulta = $this->historiaService->registrarConsulta($request->all());
        return ApiResponse::created($consulta, 'Consulta registrada');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(['id' => $id], 'Detalle de consulta');
    }
}