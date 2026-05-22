<?php

namespace App\Http\Controllers\Estructura;

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
     */
    public function index(Request $request): JsonResponse
    {
        $extensiones = ExtensionTelefonica::with('unidadAdministrativa')
            ->activas()
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
