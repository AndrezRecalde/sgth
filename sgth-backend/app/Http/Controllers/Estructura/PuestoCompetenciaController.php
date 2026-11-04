<?php

namespace App\Http\Controllers\Estructura;

use App\Enums\NivelCompetencia;
use App\Http\Controllers\Controller;
use App\Http\Resources\Estructura\PuestoCompetenciaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Competencia;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoCompetencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Las competencias técnicas y conductuales que exige un puesto, con su nivel.
 * Son las que el jefe copia en el FOR-02 de quien lo ocupa.
 *
 * Se leen como el puesto (`ver-estructura`) y se escriben como el puesto
 * (`gestionar-puestos`). No las lee el Dispensario: a la matriz de riesgos del
 * FEMO le bastan las actividades.
 */
final class PuestoCompetenciaController extends Controller
{
    public function index(Request $request, int $puestoId): JsonResponse
    {
        $this->authorize('view', Puesto::findOrFail($puestoId));

        $competencias = PuestoCompetencia::query()
            ->where('puesto_competencias.puesto_id', $puestoId)
            ->join('competencias', 'competencias.id', '=', 'puesto_competencias.competencia_id')
            // Primero las técnicas, cada tipo en el orden del diccionario.
            ->orderByDesc('competencias.tipo')
            ->orderBy('competencias.numero')
            ->select('puesto_competencias.*')
            ->with('competencia')
            ->get();

        return ApiResponse::ok(
            PuestoCompetenciaResource::collection($competencias),
            'Competencias del puesto.',
        );
    }

    public function store(Request $request, int $puestoId): JsonResponse
    {
        $puesto = Puesto::findOrFail($puestoId);
        $this->authorize('update', $puesto);

        $datos = $request->validate([
            'competencia_id' => ['required', 'integer', 'exists:competencias,id'],
            'nivel'          => ['required', Rule::enum(NivelCompetencia::class)],
        ]);

        $competencia = Competencia::findOrFail($datos['competencia_id']);

        if (! $competencia->activo) {
            throw ValidationException::withMessages([
                'competencia_id' => 'La competencia está retirada del diccionario.',
            ]);
        }

        if ($puesto->competencias()->where('competencia_id', $competencia->id)->exists()) {
            throw ValidationException::withMessages([
                'competencia_id' => 'El puesto ya exige esta competencia. Cambie su nivel en vez de agregarla otra vez.',
            ]);
        }

        $asignada = $puesto->competencias()->create($datos)->load('competencia');

        return ApiResponse::created(
            new PuestoCompetenciaResource($asignada),
            'Competencia agregada al puesto.',
        );
    }

    public function update(Request $request, int $puestoId, int $id): JsonResponse
    {
        $this->authorize('update', Puesto::findOrFail($puestoId));

        $datos = $request->validate([
            'nivel' => ['required', Rule::enum(NivelCompetencia::class)],
        ]);

        $asignada = PuestoCompetencia::where('puesto_id', $puestoId)->findOrFail($id);
        $asignada->update($datos);

        return ApiResponse::ok(
            new PuestoCompetenciaResource($asignada->load('competencia')),
            'Nivel actualizado.',
        );
    }

    public function destroy(int $puestoId, int $id): JsonResponse
    {
        $this->authorize('update', Puesto::findOrFail($puestoId));

        PuestoCompetencia::where('puesto_id', $puestoId)->findOrFail($id)->delete();

        return ApiResponse::ok(null, 'Competencia quitada del puesto.');
    }
}
