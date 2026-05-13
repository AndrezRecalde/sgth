<?php
namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class BienInformaticoController extends Controller
{
    public function __construct(
        private readonly InventarioTiServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([], 'Bienes listados');
    }

    public function store(Request $request): JsonResponse
    {
        $bien = $this->service->registrarBien($request->all());
        return ApiResponse::created($bien, 'Bien registrado y QR generado');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(['id' => $id], 'Detalle de bien');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return ApiResponse::ok([], 'Bien actualizado');
    }

    public function destroy(int $id): JsonResponse
    {
        return ApiResponse::ok([], 'Bien dado de baja');
    }
}
