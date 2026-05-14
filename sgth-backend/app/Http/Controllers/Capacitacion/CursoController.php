<?php

namespace App\Http\Controllers\Capacitacion;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Capacitacion\Curso;
use App\Models\Capacitacion\InscripcionCurso;
use App\Contracts\Capacitacion\CapacitacionServiceInterface;
use Illuminate\Http\Request;

class CursoController extends Controller
{
    public function __construct(private readonly CapacitacionServiceInterface $service)
    {
    }

    public function index()
    {
        // Listar cursos, opcionalmente filtrando por plan activo u otros parámetros
        $cursos = Curso::with('plan')->latest()->get();
        return ApiResponse::ok($cursos, 'Cursos listados correctamente.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'plan_capacitacion_id' => 'required|exists:planes_capacitacion,id',
            'nombre' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'modalidad' => 'required|string|max:50',
            'estado' => 'nullable|string|max:50',
            'costo_por_participante' => 'numeric|min:0',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'proveedor' => 'nullable|string|max:150'
        ]);

        $curso = Curso::create($validated);
        return ApiResponse::created($curso, 'Curso registrado exitosamente.');
    }

    public function show(int $id)
    {
        $curso = Curso::with('inscripciones.servidor')->findOrFail($id);
        return ApiResponse::ok($curso, 'Detalle del curso con sus inscritos.');
    }

    public function update(Request $request, int $id)
    {
        $curso = Curso::findOrFail($id);

        $validated = $request->validate([
            'plan_capacitacion_id' => 'exists:planes_capacitacion,id',
            'nombre' => 'string|max:150',
            'descripcion' => 'nullable|string',
            'modalidad' => 'string|max:50',
            'estado' => 'string|max:50',
            'costo_por_participante' => 'numeric|min:0',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'proveedor' => 'nullable|string|max:150'
        ]);

        $curso->update($validated);
        return ApiResponse::ok($curso, 'Curso actualizado exitosamente.');
    }

    public function destroy(int $id)
    {
        $curso = Curso::findOrFail($id);
        // Si hay lógica compleja, se movería al service. En este caso eliminamos el recurso.
        $curso->delete();
        return ApiResponse::ok(null, 'Curso eliminado (cancelado) exitosamente.');
    }

    public function inscribir(Request $request, int $cursoId)
    {
        $validated = $request->validate([
            'servidor_id' => 'required|exists:servidores,id'
        ]);

        $curso = Curso::findOrFail($cursoId);

        $inscripcion = InscripcionCurso::firstOrCreate([
            'curso_id' => $curso->id,
            'servidor_id' => $validated['servidor_id']
        ], [
            'estado' => 'preinscrito'
        ]);

        return ApiResponse::ok($inscripcion, 'Servidor inscrito exitosamente al curso.');
    }

    public function evaluar(Request $request, int $cursoId)
    {
        $validated = $request->validate([
            'evaluaciones' => 'required|array',
            'evaluaciones.*.inscripcion_id' => 'required|exists:inscripciones_curso,id',
            'evaluaciones.*.nota' => 'required|numeric|min:0|max:10'
        ]);

        $resultados = [];
        foreach ($validated['evaluaciones'] as $eval) {
            // Delega la responsabilidad de aplicar Kirkpatrick (Nivel 2) y Emitir el PDF al Service
            $resultados[] = $this->service->registrarNotaYCertificar($eval['inscripcion_id'], $eval['nota']);
        }

        return ApiResponse::ok($resultados, 'Evaluaciones registradas y certificados emitidos.');
    }
}