<?php

namespace App\Http\Controllers\Helpdesk;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Helpdesk\Sla;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SlaController extends Controller
{
    public function index()
    {
        $slas = Sla::latest()->get();
        return ApiResponse::ok($slas, 'Configuraciones de SLA listadas correctamente.');
    }

    public function store(Request $request)
    {
        // `categoria_id` se exigía y no existe: ni en la tabla `slas` ni en el
        // `fillable` del modelo. Quien quisiera crear un SLA tenía que inventar
        // una categoría que después Eloquent descartaba en silencio. El SLA va
        // por prioridad, y la prioridad es única.
        $validated = $request->validate([
            'prioridad' => 'required|string|max:50|unique:slas,prioridad',
            'tiempo_resolucion_horas' => 'required|numeric|min:0.5',
            'tiempo_respuesta_horas' => 'required|numeric|min:0.1'
        ]);

        $sla = Sla::create($validated);
        
        return ApiResponse::created($sla, 'Configuración de SLA creada exitosamente.');
    }

    /** El `apiResource` declaraba esta ruta y el método no existía: 500 seguro. */
    public function show(int $id)
    {
        $registro = Sla::findOrFail($id);

        return ApiResponse::ok($registro, 'Configuración de SLA obtenida correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $sla = Sla::findOrFail($id);

        $validated = $request->validate([
            // Mismo campo fantasma que en el alta.
            'prioridad' => [
                'string', 'max:50', Rule::unique('slas')->ignore($sla->id),
            ],
            'tiempo_resolucion_horas' => 'numeric|min:0.5',
            'tiempo_respuesta_horas' => 'numeric|min:0.1'
        ]);

        $sla->update($validated);
        
        return ApiResponse::ok($sla, 'Configuración de SLA actualizada exitosamente.');
    }

    public function destroy(int $id)
    {
        $sla = Sla::findOrFail($id);
        $sla->delete();
        
        return ApiResponse::ok(null, 'Configuración de SLA eliminada exitosamente.');
    }
}
