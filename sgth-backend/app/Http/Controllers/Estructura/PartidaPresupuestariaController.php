<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\StorePartidaPresupuestariaRequest;
use App\Http\Requests\Estructura\UpdatePartidaPresupuestariaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\PartidaPresupuestaria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de partidas presupuestarias. Es un catálogo puro: código,
 * descripción y grupo de gasto, más dos banderas — 'activo' (sigue en uso)
 * y 'disponible' (el área presupuestaria verificó que tiene fondos). No
 * lleva monto asignado ni saldo por año fiscal: confirmado con Talento
 * Humano que no lo necesitan, y el guard del Art. 105 LOSEP en
 * MovimientoPersonalStateService se apoya solo en 'disponible'.
 */
class PartidaPresupuestariaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PartidaPresupuestaria::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $termino = $request->input('search');
                $q->where(function ($sq) use ($termino) {
                    $sq->where('codigo', 'ilike', "%{$termino}%")
                       ->orWhere('descripcion', 'ilike', "%{$termino}%");
                });
            })
            ->when($request->filled('grupo_gasto'), fn ($q) => $q->where('grupo_gasto', $request->input('grupo_gasto')))
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->when($request->filled('disponible'), fn ($q) => $q->where('disponible', $request->boolean('disponible')))
            ->orderBy('codigo');

        // 'all' para alimentar los selectores (Puestos, acciones de
        // personal); paginado para la pantalla de administración.
        $partidas = $request->boolean('all')
            ? $query->get()
            : $query->paginate($request->integer('per_page', 20));

        return ApiResponse::ok($partidas, 'Catálogo de partidas presupuestarias.');
    }

    public function store(StorePartidaPresupuestariaRequest $request): JsonResponse
    {
        $partida = PartidaPresupuestaria::create($request->validated());

        return ApiResponse::created($partida, 'Partida presupuestaria creada.');
    }

    public function show(PartidaPresupuestaria $partida): JsonResponse
    {
        $partida->loadCount('puestos');

        return ApiResponse::ok($partida, 'Partida presupuestaria.');
    }

    public function update(
        UpdatePartidaPresupuestariaRequest $request,
        PartidaPresupuestaria $partida
    ): JsonResponse {
        $partida->update($request->validated());

        return ApiResponse::ok($partida, 'Partida presupuestaria actualizada.');
    }

    /**
     * Solo se elimina una partida que no respalde ningún puesto. Si ya está
     * en uso, lo correcto es desactivarla (activo = false) para que deje de
     * ofrecerse en los selectores sin romper el histórico.
     */
    public function destroy(PartidaPresupuestaria $partida): JsonResponse
    {
        if ($partida->puestos()->exists()) {
            return ApiResponse::error(
                'No se puede eliminar la partida porque tiene puestos asignados. Desactívela en su lugar.',
                null, 422
            );
        }

        $partida->delete();

        return ApiResponse::ok(null, 'Partida presupuestaria eliminada.');
    }
}
