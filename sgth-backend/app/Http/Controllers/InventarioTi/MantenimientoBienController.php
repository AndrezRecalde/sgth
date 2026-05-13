<?php
namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class MantenimientoBienController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([], 'Mantenimientos listados');
    }

    public function store(Request $request): JsonResponse
    {
        return ApiResponse::created([], 'Mantenimiento programado');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(['id' => $id], 'Detalle de mantenimiento');
    }
}
