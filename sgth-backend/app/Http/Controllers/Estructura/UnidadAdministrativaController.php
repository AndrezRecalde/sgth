<?php

namespace App\Http\Controllers\Estructura;

use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\StoreUnidadAdministrativaRequest;
use App\Http\Requests\Estructura\UpdateUnidadAdministrativaRequest;
use App\Http\Resources\Estructura\UnidadAdministrativaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UnidadAdministrativaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EstructuraServiceInterface $estructuraService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UnidadAdministrativa::class);
        $unidades = $this->estructuraService->listarUnidades($request->all());
        return ApiResponse::ok(UnidadAdministrativaResource::collection($unidades), 'Unidades administrativas obtenidas exitosamente');
    }

    public function todas(Request $request): JsonResponse
    {
        $unidades = $this->estructuraService->listarUnidadesTodas(
            $request->all()
        );
        return ApiResponse::ok($unidades, 'Unidades listadas.');
    }

    /**
     * Código sugerido para una unidad nueva bajo el padre indicado.
     *
     * Es una sugerencia, no una asignación: el formulario lo rellena y quien
     * registra puede cambiarlo, porque el código a veces tiene que coincidir
     * con el orgánico funcional aprobado o con la codificación presupuestaria.
     */
    public function sugerirCodigo(Request $request): JsonResponse
    {
        $this->authorize('create', UnidadAdministrativa::class);

        $validado = $request->validate([
            'unidad_padre_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
        ]);

        return ApiResponse::ok(
            ['codigo' => $this->estructuraService->sugerirCodigo(
                $validado['unidad_padre_id'] ?? null
            )],
            'Código sugerido.'
        );
    }

    public function store(StoreUnidadAdministrativaRequest $request): JsonResponse
    {
        // La autorización ya está delegada al FormRequest
        $unidad = $this->estructuraService->crearUnidad($request->validated());
        return ApiResponse::created(new UnidadAdministrativaResource($unidad), 'Unidad administrativa creada exitosamente');
    }

    public function show(UnidadAdministrativa $unidades_administrativa): JsonResponse
    {
        $this->authorize('view', $unidades_administrativa);
        $unidad = $this->estructuraService->obtenerUnidad($unidades_administrativa->id);
        return ApiResponse::ok(new UnidadAdministrativaResource($unidad), 'Unidad administrativa obtenida exitosamente');
    }

    public function update(UpdateUnidadAdministrativaRequest $request, UnidadAdministrativa $unidades_administrativa): JsonResponse
    {
        // La autorización ya está delegada al FormRequest
        $unidad = $this->estructuraService->actualizarUnidad($unidades_administrativa->id, $request->validated());
        return ApiResponse::ok(new UnidadAdministrativaResource($unidad), 'Unidad administrativa actualizada exitosamente');
    }

    public function destroy(UnidadAdministrativa $unidades_administrativa): JsonResponse
    {
        $this->authorize('delete', $unidades_administrativa);
        $this->estructuraService->eliminarUnidad($unidades_administrativa->id);
        return ApiResponse::ok(null, 'Unidad administrativa eliminada exitosamente');
    }
}
