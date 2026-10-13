<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\MantenimientoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\StoreMantenimientoRequest;
use App\Http\Resources\InventarioTi\MantenimientoBienResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los mantenimientos de los equipos (fase 1.5). Los tres métodos eran
 * muñones: `[]`, «Mantenimiento programado» sin guardar y el eco del id.
 */
final class MantenimientoBienController extends Controller
{
    public function __construct(private readonly MantenimientoServiceInterface $mantenimientos) {}

    public function index(Request $request): JsonResponse
    {
        $pagina = $this->mantenimientos->listar($request->query());

        return ApiResponse::paginadoDe(
            $pagina,
            MantenimientoBienResource::collection($pagina->getCollection())->resolve($request),
            'Mantenimientos de equipos.'
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(new MantenimientoBienResource($this->mantenimientos->obtener($id)), 'Mantenimiento.');
    }

    public function store(StoreMantenimientoRequest $request): JsonResponse
    {
        return ApiResponse::created(
            new MantenimientoBienResource($this->mantenimientos->registrar($request->validated())),
            'Mantenimiento registrado.'
        );
    }

    public function anular(Request $request, MantenimientoBien $mantenimiento): JsonResponse
    {
        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:5', 'max:500']],
            [],
            ['motivo' => 'motivo de la anulación'],
        );

        return ApiResponse::ok(
            new MantenimientoBienResource($this->mantenimientos->anular($mantenimiento, $datos['motivo'])),
            'Mantenimiento anulado.'
        );
    }

    /** Quiénes pueden figurar como técnico: los que registran mantenimientos. */
    public function tecnicos(): JsonResponse
    {
        return ApiResponse::ok(
            $this->mantenimientos->tecnicos()->map(fn (User $u) => ['id' => $u->id, 'nombre' => $u->nombre_completo])->values(),
            'Técnicos de TIC.'
        );
    }
}
