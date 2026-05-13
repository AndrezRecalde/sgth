<?php
namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class AsignacionBienController extends Controller
{
    public function __construct(
        private readonly InventarioTiServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([], 'Asignaciones listadas');
    }

    public function store(Request $request): JsonResponse
    {
        $asignacion = $this->service->asignarBien($request->all());
        return ApiResponse::created($asignacion, 'Asignación creada y Acta PDF generada');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(['id' => $id], 'Detalle de asignación');
    }
}
