<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\TecnicoDtic;
use App\Contracts\Helpdesk\HelpdeskServiceInterface;
use Illuminate\Http\Request;

class TecnicoDticController extends Controller
{
    public function __construct(private readonly HelpdeskServiceInterface $service)
    {
    }

    public function index()
    {
        // La relación es `user`, no `servidor`: la tabla guarda `user_id`. Con
        // `servidor` el listado reventaba en cuanto había un técnico, y sobre
        // base vacía no se notaba porque Eloquent no llega a resolverla.
        $tecnicos = TecnicoDtic::with('user.servidor')->latest()->get();
        return ApiResponse::ok($tecnicos, 'Técnicos DTIC listados correctamente.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Era `servidor_id`, columna que esta tabla no tiene: la propia
            // regla `unique` consultaba por ella y reventaba, así que dar de
            // alta un técnico era imposible.
            'user_id' => 'required|exists:users,id|unique:tecnicos_dtic,user_id',
            'area_dtic_id' => 'required|exists:areas_dtic,id',
            'nivel' => 'required|integer|min:1|max:3',
            'estado' => 'nullable|string|max:50'
        ]);

        $tecnico = TecnicoDtic::create($validated);
        
        return ApiResponse::created($tecnico, 'Técnico registrado exitosamente.');
    }

    /** El `apiResource` declaraba esta ruta y el método no existía: 500 seguro. */
    public function show(int $id)
    {
        $tecnico = TecnicoDtic::with('user.servidor')->findOrFail($id);

        return ApiResponse::ok($tecnico, 'Técnico DTIC obtenido correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $tecnico = TecnicoDtic::findOrFail($id);

        $validated = $request->validate([
            'area_dtic_id' => 'exists:areas_dtic,id',
            'nivel' => 'integer|min:1|max:3',
            'estado' => 'string|max:50'
        ]);

        $tecnico->update($validated);
        return ApiResponse::ok($tecnico, 'Datos del técnico actualizados.');
    }

    public function destroy(int $id)
    {
        $tecnico = TecnicoDtic::findOrFail($id);
        $tecnico->delete();
        return ApiResponse::ok(null, 'Técnico desactivado exitosamente.');
    }

    public function cargaTrabajo(int $id)
    {
        // Delegamos al servicio el cálculo de tickets asignados y métricas de rendimiento
        $metricas = $this->service->obtenerCargaTrabajoYMetricas($id);

        return ApiResponse::ok($metricas, 'Carga de trabajo y métricas obtenidas correctamente.');
    }
}
