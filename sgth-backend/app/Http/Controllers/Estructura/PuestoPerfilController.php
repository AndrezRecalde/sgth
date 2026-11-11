<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estructura\GuardarPerfilPuestoRequest;
use App\Http\Resources\Estructura\PuestoPerfilResource;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Puesto;
use App\Services\Estructura\PerfilDelPuestoService;
use Illuminate\Http\JsonResponse;

/**
 * La descripción del puesto (docs/diseno-manual-de-puestos.md, P1): misión,
 * relaciones, requisitos y capacitación. Se lee como el puesto (`view`,
 * `ver-estructura`) y se escribe como el puesto (`update`, `gestionar-puestos`).
 */
final class PuestoPerfilController extends Controller
{
    public function __construct(private readonly PerfilDelPuestoService $perfiles)
    {
    }

    public function show(int $puestoId): JsonResponse
    {
        $puesto = Puesto::findOrFail($puestoId);
        $this->authorize('view', $puesto);

        return ApiResponse::ok(new PuestoPerfilResource($this->perfiles->obtener($puesto)), 'Perfil del puesto.');
    }

    /** El permiso lo comprueba la petición, antes de validar. */
    public function update(GuardarPerfilPuestoRequest $request, int $puestoId): JsonResponse
    {
        $puesto = Puesto::findOrFail($puestoId);

        return ApiResponse::ok(
            new PuestoPerfilResource($this->perfiles->guardar($puesto, $request->validated())),
            'Perfil del puesto guardado.',
        );
    }
}
