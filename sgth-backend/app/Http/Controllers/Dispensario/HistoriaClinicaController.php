<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class HistoriaClinicaController extends Controller
{
    public function __construct(
        private readonly HistoriaClinicaServiceInterface $historiaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([], 'Listado de historias');
    }

    public function store(Request $request): JsonResponse
    {
        $historia = $this->historiaService->crearHistoria($request->all());
        return ApiResponse::created($historia, 'Historia clínica creada');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(['id' => $id], 'Detalle de historia');
    }
}