<?php

namespace App\Http\Controllers\Viatico;

use App\Http\Controllers\Controller;
use App\Http\Resources\Viatico\ViaticoBandejaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Viatico\Viatico;
use App\Services\Viatico\BandejaViaticoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La bandeja de Financiero. Las reglas de cada etapa, los montos y el plazo
 * están en `BandejaViaticoService`.
 */
class BandejaViaticoController extends Controller
{
    public function __construct(private BandejaViaticoService $bandeja) {}

    public function resumen(Request $request): JsonResponse
    {
        $this->authorize('verBandeja', Viatico::class);

        return ApiResponse::ok(
            $this->bandeja->resumen($this->filtros($request)),
            'Resumen de la bandeja de viáticos.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('verBandeja', Viatico::class);

        $datos = $request->validate([
            'etapa'    => ['required', Rule::in(BandejaViaticoService::ETAPAS)],
            'vencidas' => ['sometimes', 'boolean'],
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $pagina = $this->bandeja->listar(
            $datos['etapa'],
            [...$this->filtros($request), 'vencidas' => (bool) ($datos['vencidas'] ?? false)],
            (int) ($datos['per_page'] ?? 15),
            (int) ($datos['page'] ?? 1),
        );

        return ApiResponse::paginado(
            $pagina->through(fn (Viatico $viatico) => new ViaticoBandejaResource($viatico)),
            'Viáticos de la bandeja.'
        );
    }

    /** @return array{unidad_id: ?int, desde: ?string, hasta: ?string, search: ?string} */
    private function filtros(Request $request): array
    {
        $datos = $request->validate([
            'unidad_id' => ['nullable', 'integer'],
            'desde'     => ['nullable', 'date'],
            'hasta'     => ['nullable', 'date', 'after_or_equal:desde'],
            'search'    => ['nullable', 'string', 'max:100'],
        ]);

        return [
            'unidad_id' => isset($datos['unidad_id']) ? (int) $datos['unidad_id'] : null,
            'desde'     => $datos['desde'] ?? null,
            'hasta'     => $datos['hasta'] ?? null,
            'search'    => $datos['search'] ?? null,
        ];
    }
}
