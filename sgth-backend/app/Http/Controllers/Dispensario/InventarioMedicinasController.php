<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\InventarioMedicinasServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class InventarioMedicinasController extends Controller
{
    public function __construct(
        private readonly InventarioMedicinasServiceInterface $inventarioService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([], 'Listado de medicinas');
    }

    public function store(Request $request): JsonResponse
    {
        $medicina = $this->inventarioService->ingresarMedicina($request->all());
        return ApiResponse::created($medicina, 'Medicina ingresada');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return ApiResponse::ok([], 'Medicina actualizada');
    }

    public function destroy(int $id): JsonResponse
    {
        return ApiResponse::ok([], 'Medicina dada de baja');
    }

    public function kardex(int $id): JsonResponse
    {
        return ApiResponse::ok([], 'Movimientos de kardex');
    }
}