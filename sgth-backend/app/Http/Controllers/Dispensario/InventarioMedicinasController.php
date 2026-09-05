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

    public function contarStockBajo(): JsonResponse
    {
        return ApiResponse::ok(
            ['total' => $this->inventarioService->contarStockBajo()]
        );
    }

    public function buscar(Request $request): JsonResponse
    {
        $request->validate([
            'q'                => ['required', 'string', 'min:2'],
            'incluir_agotadas' => ['nullable', 'boolean'],
        ]);

        $resultados = $this->inventarioService->buscar(
            $request->string('q')->value(),
            soloDespachables: !$request->boolean('incluir_agotadas')
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

    public function registrarBaja(
        Request $request,
        int $medicina
    ): JsonResponse {
        $request->validate([
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo'   => ['required', 'string', 'max:255'],
            // Opcional: sin lote sale por FEFO, que es lo que sirve para tirar
            // lo vencido. Con lote sale de ese, para una rotura o una retirada.
            'lote_id'  => ['nullable', 'integer', 'exists:lotes_medicina,id'],
        ]);

        $actualizado = $this->inventarioService->registrarBaja(
            $medicina,
            $request->integer('cantidad'),
            $request->string('motivo')->value(),
            $request->user()->id,
            $request->filled('lote_id') ? $request->integer('lote_id') : null
        );

        return ApiResponse::ok(
            $actualizado, 'Existencias dadas de baja correctamente.'
        );
    }

    public function ajustarInventario(
        Request $request,
        int $medicina
    ): JsonResponse {
        $request->validate([
            'nuevo_stock' => ['required', 'integer', 'min:0'],
            'motivo'      => ['required', 'string', 'max:255'],
        ]);

        $actualizado = $this->inventarioService->ajustarInventario(
            $medicina,
            $request->integer('nuevo_stock'),
            $request->string('motivo')->value(),
            $request->user()->id
        );

        return ApiResponse::ok(
            $actualizado, 'Inventario ajustado correctamente.'
        );
    }

    public function destroy(int $medicina): JsonResponse
    {
        $actualizado = $this->inventarioService->darDeBaja($medicina);

        $mensaje = $actualizado->estado
            ? 'Medicina reactivada.'
            : 'Medicina retirada del catálogo.';

        return ApiResponse::ok($actualizado, $mensaje);
    }

    public function kardex(Request $request, int $id): JsonResponse
    {
        $movimientos = $this->inventarioService->kardex(
            $id,
            $request->integer('per_page', 20)
        );

        return ApiResponse::ok($movimientos, 'Kardex de la medicina.');
    }
}