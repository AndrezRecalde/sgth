<?php

namespace App\Http\Controllers\Estructura;

use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Resources\Estructura\ExtensionTelefonicaResource;
use App\Models\Estructura\ExtensionTelefonica;
use App\Http\Requests\Estructura\StoreExtensionTelefonicaRequest;
use App\Http\Requests\Estructura\UpdateExtensionTelefonicaRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtensionTelefonicaController extends Controller
{
    /**
     * Directorio telefónico — lista plana con filtros opcionales.
     *
     * Las inactivas, solo para quien mantiene el directorio y las pide con
     * `incluir_inactivas`: sin eso, la extensión que se desactivaba desaparecía
     * también de la pantalla de gestión y no había forma de reactivarla.
     */
    public function index(Request $request): JsonResponse
    {
        $conInactivas = $request->boolean('incluir_inactivas')
            && $request->user()->can(Permiso::GESTIONAR_DIRECTORIO->value);

        $extensiones = ExtensionTelefonica::with('unidadAdministrativa')
            ->when(! $conInactivas, fn($q) => $q->activas())
            ->when(
                $request->filled('search'),
                fn($q) => $q->where(function ($q) use ($request) {
                    $q->where('numero_extension', 'ilike', "%{$request->search}%")
                      ->orWhere('responsable', 'ilike', "%{$request->search}%")
                      ->orWhereHas('unidadAdministrativa', fn($q) =>
                          $q->where('nombre', 'ilike', "%{$request->search}%")
                      );
                })
            )
            ->when(
                $request->filled('unidad_administrativa_id'),
                fn($q) => $q->where(
                    'unidad_administrativa_id',
                    $request->unidad_administrativa_id
                )
            )
            ->orderBy('numero_extension')
            ->orderBy('id')
            ->get();

        return ApiResponse::ok(
            ExtensionTelefonicaResource::collection($extensiones),
            'Directorio telefónico recuperado correctamente.'
        );
    }

    public function store(StoreExtensionTelefonicaRequest $request): JsonResponse
    {
        $extension = ExtensionTelefonica::create($request->validated());
        $extension->load('unidadAdministrativa');

        return ApiResponse::created(
            new ExtensionTelefonicaResource($extension),
            'Extensión telefónica registrada correctamente.'
        );
    }

    public function update(
        UpdateExtensionTelefonicaRequest $request,
        int $id
    ): JsonResponse {
        $extension = ExtensionTelefonica::findOrFail($id);
        $extension->update($request->validated());
        $extension->load('unidadAdministrativa');

        return ApiResponse::ok(
            new ExtensionTelefonicaResource($extension),
            'Extensión telefónica actualizada correctamente.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $extension = ExtensionTelefonica::findOrFail($id);
        $extension->delete();

        return ApiResponse::ok(null, 'Extensión telefónica eliminada correctamente.');
    }
}
