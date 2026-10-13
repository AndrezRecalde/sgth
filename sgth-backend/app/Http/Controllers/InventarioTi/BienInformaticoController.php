<?php

namespace App\Http\Controllers\InventarioTi;

use App\Contracts\InventarioTi\BienInformaticoServiceInterface;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\InventarioTi\StoreBienInformaticoRequest;
use App\Http\Requests\InventarioTi\UpdateBienInformaticoRequest;
use App\Http\Resources\InventarioTi\BienInformaticoResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BienInformaticoController extends Controller
{
    public function __construct(
        private readonly BienInformaticoServiceInterface $bienes,
        private readonly InventarioTiServiceInterface $inventario,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $bienes = $this->bienes->listar($request->query());

        return ApiResponse::paginadoDe(
            $bienes,
            BienInformaticoResource::collection($bienes->getCollection())->resolve($request),
            'Equipos del inventario.'
        );
    }

    public function store(StoreBienInformaticoRequest $request): JsonResponse
    {
        $bien = $this->bienes->registrar($request->validated());

        return ApiResponse::created(new BienInformaticoResource($bien), 'Equipo registrado.');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(new BienInformaticoResource($this->bienes->obtener($id)), 'Ficha del equipo.');
    }

    public function update(UpdateBienInformaticoRequest $request, int $id): JsonResponse
    {
        $bien = $this->bienes->actualizar($id, $request->validated());

        return ApiResponse::ok(new BienInformaticoResource($bien), 'Equipo actualizado.');
    }

    /**
     * Borra la ficha de un equipo registrado por error, y solo mientras no
     * tenga historial. La baja —el equipo que sale del servicio— va por
     * «bajas».
     */
    public function destroy(int $id): JsonResponse
    {
        $this->bienes->retirar($id);

        return ApiResponse::ok(null, 'Equipo retirado del inventario.');
    }

    /** La vida del equipo: quién lo ha tenido y qué se le ha hecho. */
    public function historial(int $id): JsonResponse
    {
        $ficha = $this->inventario->obtenerFichaTecnicaCompleta([
            'bien_informatico_id' => $id,
        ]);

        return ApiResponse::ok($ficha, 'Historial del bien informático.');
    }
}
