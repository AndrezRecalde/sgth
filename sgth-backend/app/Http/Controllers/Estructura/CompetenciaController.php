<?php

namespace App\Http\Controllers\Estructura;

use App\Enums\TipoCompetencia;
use App\Http\Controllers\Controller;
use App\Http\Resources\Estructura\CompetenciaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Estructura\Competencia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * El diccionario de competencias del Ministerio, de donde salen las
 * competencias de cada puesto.
 *
 * El número de una competencia no se elige ni se cambia: es el siguiente de
 * su tipo, y de él sale el del comportamiento que se escribe en el FOR-02. Una
 * competencia que algún puesto exige no se elimina; se retira, y deja de
 * ofrecerse.
 */
final class CompetenciaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Competencia::class);

        $filtros = $request->validate([
            'tipo'         => ['nullable', Rule::enum(TipoCompetencia::class)],
            // axios manda el booleano como «true»; la regla `boolean` no lo
            // acepta en una consulta, y `$request->boolean()` sí lo lee.
            'solo_activas' => ['nullable', 'in:0,1,true,false'],
            'buscar'       => ['nullable', 'string', 'max:100'],
        ]);

        $competencias = Competencia::query()
            ->withCount('puestos')
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($request->boolean('solo_activas'), fn ($q) => $q->where('activo', true))
            ->when($filtros['buscar'] ?? null, fn ($q, $texto) => $q->where('nombre', 'ilike', "%{$texto}%"))
            // Primero las técnicas, como en el diccionario.
            ->orderByDesc('tipo')
            ->orderBy('numero')
            ->get();

        return ApiResponse::ok(
            CompetenciaResource::collection($competencias),
            'Diccionario de competencias.',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Competencia::class);

        $datos = $request->validate([
            'tipo'                 => ['required', Rule::enum(TipoCompetencia::class)],
            'nombre'               => [
                'required', 'string', 'max:150',
                Rule::unique('competencias', 'nombre')->where('tipo', $request->input('tipo')),
            ],
            'definicion'           => ['nullable', 'string', 'max:2000'],
            'comportamiento_alto'  => ['required', 'string', 'max:2000'],
            'comportamiento_medio' => ['required', 'string', 'max:2000'],
            'comportamiento_bajo'  => ['required', 'string', 'max:2000'],
        ]);

        $competencia = DB::transaction(function () use ($datos) {
            // PostgreSQL no bloquea un `max()`: se bloquea la última fila.
            $ultimo = Competencia::where('tipo', $datos['tipo'])
                ->orderByDesc('numero')
                ->lockForUpdate()
                ->value('numero') ?? 0;

            return Competencia::create($datos + ['numero' => $ultimo + 1, 'activo' => true]);
        });

        return ApiResponse::created(
            new CompetenciaResource($competencia->loadCount('puestos')),
            'Competencia agregada al diccionario.',
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $competencia = Competencia::findOrFail($id);
        $this->authorize('update', $competencia);

        $datos = $request->validate([
            'nombre'               => [
                'sometimes', 'string', 'max:150',
                Rule::unique('competencias', 'nombre')
                    ->where('tipo', $competencia->tipo->value)
                    ->ignore($competencia->id),
            ],
            'definicion'           => ['sometimes', 'nullable', 'string', 'max:2000'],
            'comportamiento_alto'  => ['sometimes', 'string', 'max:2000'],
            'comportamiento_medio' => ['sometimes', 'string', 'max:2000'],
            'comportamiento_bajo'  => ['sometimes', 'string', 'max:2000'],
            'activo'               => ['sometimes', 'boolean'],
        ]);

        $competencia->update($datos);

        return ApiResponse::ok(
            new CompetenciaResource($competencia->loadCount('puestos')),
            'Competencia actualizada.',
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $competencia = Competencia::withCount('puestos')->findOrFail($id);
        $this->authorize('delete', $competencia);

        if ($competencia->puestos_count > 0) {
            return ApiResponse::error(
                'La competencia la exige algún puesto: retírela del diccionario en vez de eliminarla.',
                null, 422
            );
        }

        $competencia->delete();

        return ApiResponse::ok(null, 'Competencia eliminada.');
    }
}
