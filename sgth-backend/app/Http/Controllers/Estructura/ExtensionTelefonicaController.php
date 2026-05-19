<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Models\Estructura\ExtensionTelefonica;
use App\Http\Requests\Estructura\StoreExtensionTelefonicaRequest;
use App\Http\Requests\Estructura\UpdateExtensionTelefonicaRequest;
use App\Http\Responses\ApiResponse;

class ExtensionTelefonicaController extends Controller
{
    /**
     * Display a listing of the resource.
     * Directorio completo agrupado por unidad administrativa.
     */
    public function index()
    {
        $extensiones = ExtensionTelefonica::with('unidadAdministrativa')
            ->activas()
            ->get()
            ->sortBy(function ($ext) {
                return $ext->unidadAdministrativa ? $ext->unidadAdministrativa->nombre : '';
            })
            ->sortBy('numero_extension')
            ->groupBy(function ($ext) {
                return $ext->unidadAdministrativa ? $ext->unidadAdministrativa->nombre : 'Sin Unidad';
            });

        return ApiResponse::ok($extensiones, 'Directorio telefónico recuperado correctamente');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExtensionTelefonicaRequest $request)
    {
        $extension = ExtensionTelefonica::create($request->validated());
        
        return ApiResponse::created($extension, 'Extensión telefónica registrada correctamente');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExtensionTelefonicaRequest $request, int $id)
    {
        $extension = ExtensionTelefonica::findOrFail($id);
        $extension->update($request->validated());

        return ApiResponse::ok($extension, 'Extensión telefónica actualizada correctamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $extension = ExtensionTelefonica::findOrFail($id);
        $extension->delete();

        return ApiResponse::ok(null, 'Extensión telefónica eliminada correctamente');
    }
}
