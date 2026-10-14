<?php

namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\UnidadAdministrativa;
use App\Services\InventarioTi\UbicacionesBienService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * La ubicación sugerida de cada unidad y la mudanza en lote (decidido con TIC
 * el 2026-10-13). La ubicación es del equipo; esto solo la propone y la
 * cambia en bloque cuando una unidad se muda.
 */
final class UbicacionesBienController extends Controller
{
    public function __construct(private readonly UbicacionesBienService $ubicaciones) {}

    public function index(): JsonResponse
    {
        return ApiResponse::ok($this->ubicaciones->porUnidad(), 'Ubicación habitual de cada unidad.');
    }

    public function fijar(Request $request, UnidadAdministrativa $unidad): JsonResponse
    {
        $datos = $request->validate(['ubicacion_id' => ['present', 'nullable', 'integer', $this->ubicacionViva()]], [], ['ubicacion_id' => 'ubicación']);
        $this->ubicaciones->fijar($unidad->id, $datos['ubicacion_id']);

        return ApiResponse::ok(null, $datos['ubicacion_id'] ? 'Ubicación habitual guardada.' : 'La unidad queda sin ubicación habitual.');
    }

    /** La que se propone al registrar un equipo (por su unidad) o al entregarlo (por el custodio). */
    public function sugerida(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'unidad_administrativa_id' => ['nullable', 'integer'],
            'servidor_id'              => ['nullable', 'integer'],
        ]);
        $ubicacion = $this->ubicaciones->sugerida($datos['unidad_administrativa_id'] ?? null, $datos['servidor_id'] ?? null);

        return ApiResponse::ok($ubicacion ? ['id' => $ubicacion->id, 'nombre' => $ubicacion->nombre] : null, 'Ubicación sugerida.');
    }

    public function mudanza(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'unidad_administrativa_id' => ['required', 'integer', Rule::exists('unidades_administrativas', 'id')],
            'desde_ubicacion_id'       => ['present', 'nullable', 'integer', $this->ubicacionViva()],
            'hacia_ubicacion_id'       => ['required', 'integer', $this->ubicacionViva()],
            'aplicar'                  => ['sometimes', 'boolean'],
        ], [], [
            'unidad_administrativa_id' => 'unidad', 'desde_ubicacion_id' => 'ubicación de origen', 'hacia_ubicacion_id' => 'ubicación de destino',
        ]);
        $aplicar = (bool) ($datos['aplicar'] ?? false);

        $resultado = $this->ubicaciones->mudanza(
            (int) $datos['unidad_administrativa_id'],
            $datos['desde_ubicacion_id'] === null ? null : (int) $datos['desde_ubicacion_id'],
            (int) $datos['hacia_ubicacion_id'],
            $aplicar,
        );

        return ApiResponse::ok($resultado, $aplicar
            ? "Se movieron {$resultado['total']} equipos con sus componentes."
            : "Se moverían {$resultado['total']} equipos.");
    }

    private function ubicacionViva(): Exists
    {
        return Rule::exists('ubicaciones', 'id')->whereNull('deleted_at');
    }
}
