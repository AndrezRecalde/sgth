<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\InventarioMedicinasServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreInventarioMedicinaRequest;
use App\Http\Requests\Dispensario\UpdateInventarioMedicinaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;

final class InventarioMedicinasController extends Controller
{
    public function __construct(
        private readonly InventarioMedicinasServiceInterface $inventarioService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $medicinas = $this->inventarioService->listar(
            $request->all()
        );

        return ApiResponse::ok($medicinas, 'Listado de medicinas.');
    }

    public function buscar(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ]);

        $resultados = $this->inventarioService->buscar(
            $request->string('q')->value()
        );

        return ApiResponse::ok($resultados);
    }

    public function store(
        StoreInventarioMedicinaRequest $request
    ): JsonResponse {
        $medicina = $this->inventarioService->ingresarMedicina(
            $request->validated(),
            $request->user()->id
        );

        return ApiResponse::created(
            $medicina, 'Medicina ingresada al inventario.'
        );
    }

    public function show(int $id): JsonResponse
    {
        $medicina = $this->inventarioService->obtener($id);

        return ApiResponse::ok($medicina);
    }

    public function update(
        UpdateInventarioMedicinaRequest $request,
        int $medicina
    ): JsonResponse {
        $actualizado = $this->inventarioService->actualizar(
            $medicina, $request->validated()
        );

        return ApiResponse::ok(
            $actualizado, 'Medicina actualizada.'
        );
    }

    public function ingresarStock(
        Request $request,
        int $medicina
    ): JsonResponse {
        $request->validate([
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo'   => ['required', 'string', 'max:255'],
        ]);

        $actualizado = $this->inventarioService->ingresarStock(
            $medicina,
            $request->integer('cantidad'),
            $request->string('motivo')->value(),
            $request->user()->id
        );

        return ApiResponse::ok(
            $actualizado, 'Stock ingresado correctamente.'
        );
    }

    public function destroy(int $medicina): JsonResponse
    {
        $actualizado = $this->inventarioService->darDeBaja($medicina);

        $mensaje = $actualizado->estado
            ? 'Medicina reactivada.'
            : 'Medicina dada de baja.';

        return ApiResponse::ok($actualizado, $mensaje);
    }

    public function kardex(int $id): JsonResponse
    {
        $movimientos = $this->inventarioService->kardex($id);

        return ApiResponse::ok($movimientos);
    }
}