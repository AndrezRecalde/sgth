<?php

namespace App\Http\Controllers\Dispensario;

use App\Enums\EspecialidadAtencion;
use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StorePerfilExamenRequest;
use App\Http\Requests\Dispensario\UpdatePerfilExamenRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\PerfilExamen;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Los perfiles de exámenes: los lee todo el personal clínico para pedir, y
 * los arman el médico, el odontólogo (los de su especialidad) y la
 * administración del Dispensario (D6, D12).
 */
final class PerfilExamenController extends Controller
{
    /** Lo que se devuelve de cada examen del perfil: lo que el modal pinta. */
    private const EXAMEN = 'examenes:id,nombre,area,sensible,activo';

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'especialidad'      => ['nullable', 'string', Rule::in(EspecialidadAtencion::valores())],
            'incluir_inactivos' => ['nullable', 'boolean'],
        ]);

        $perfiles = PerfilExamen::with(self::EXAMEN)
            ->when(! $request->boolean('incluir_inactivos'), fn ($q) => $q->activos())
            ->when($request->filled('especialidad'), fn ($q) => $q->where('especialidad', $request->input('especialidad')))
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return ApiResponse::ok($perfiles, 'Perfiles de exámenes.');
    }

    public function store(StorePerfilExamenRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $especialidad = EspecialidadAtencion::from($datos['especialidad']);

        if (! $this->puedeArmar($request->user(), $especialidad)) {
            return $this->fueraDeSuEspecialidad();
        }

        $perfil = DB::transaction(function () use ($datos, $request) {
            $perfil = PerfilExamen::create([
                ...Arr::except($datos, ['examenes']),
                'created_by' => $request->user()->id,
            ]);
            $perfil->examenes()->sync($datos['examenes']);

            return $perfil;
        });

        return ApiResponse::created($perfil->refresh()->load(self::EXAMEN), 'Perfil creado.');
    }

    /**
     * Corrige un perfil o lo retira. No hay borrado: retirarlo basta para que
     * deje de ofrecerse, y las órdenes nunca lo nombran (copian sus exámenes).
     */
    public function update(UpdatePerfilExamenRequest $request, int $perfil): JsonResponse
    {
        $perfil = PerfilExamen::findOrFail($perfil);
        $datos = $request->validated();

        // Tiene que poder con la especialidad que tiene y con la que tendrá.
        $especialidades = array_filter([
            $perfil->especialidad,
            isset($datos['especialidad']) ? EspecialidadAtencion::from($datos['especialidad']) : null,
        ]);
        foreach ($especialidades as $especialidad) {
            if (! $this->puedeArmar($request->user(), $especialidad)) {
                return $this->fueraDeSuEspecialidad();
            }
        }

        DB::transaction(function () use ($perfil, $datos) {
            $perfil->update(Arr::except($datos, ['examenes']));

            if (array_key_exists('examenes', $datos)) {
                $perfil->examenes()->sync($datos['examenes']);
            }
        });

        return ApiResponse::ok($perfil->refresh()->load(self::EXAMEN), 'Perfil actualizado.');
    }

    /** La administración, cualquiera; el profesional, los de su especialidad. */
    private function puedeArmar(User $usuario, EspecialidadAtencion $especialidad): bool
    {
        return $usuario->can(Permiso::SUPERVISAR_DISPENSARIO->value)
            || $usuario->hasRole($especialidad->rol());
    }

    private function fueraDeSuEspecialidad(): JsonResponse
    {
        return ApiResponse::error(
            'Solo puede armar perfiles de su especialidad.', null, 403
        );
    }
}
