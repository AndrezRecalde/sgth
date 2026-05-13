<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\RecetaServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class RecetaController extends Controller
{
    public function __construct(
        private readonly RecetaServiceInterface $recetaService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $datosReceta = $request->except('items');
        $items = $request->input('items', []);
        $receta = $this->recetaService->emitirReceta($datosReceta, $items);
        return ApiResponse::created($receta, 'Receta emitida');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(['id' => $id], 'Detalle de receta');
    }

    public function despachar(Request $request, int $id): JsonResponse
    {
        return ApiResponse::ok([], 'Receta despachada exitosamente');
    }
}