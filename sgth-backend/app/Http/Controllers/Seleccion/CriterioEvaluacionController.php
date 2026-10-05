<?php

namespace App\Http\Controllers\Seleccion;

use App\Enums\EstadoConvocatoria;
use App\Exceptions\ReglaNegocioException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\CriterioEvaluacion;
use App\Models\Seleccion\OpcionCriterio;
use App\Services\Seleccion\CalificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Los criterios de una convocatoria (2026-10-05, decisión 5 de TH).
 *
 * En un concurso formal solo se configuran en borrador: publicado, los
 * criterios son parte de lo que se anunció. En un contenedor express, que no
 * tiene borrador, se pueden cambiar, pero un criterio con el que ya se calificó
 * no se borra: se retira (`activo = false`) y sus calificaciones quedan. No hay
 * edición: un criterio se quita y se vuelve a crear (2026-10-05). Antes borrarlo se llevaba en cascada las calificaciones de todos los
 * años sin recalcular el total de nadie.
 */
final class CriterioEvaluacionController extends Controller
{
    public function index(int $convocatoriaId): JsonResponse
    {
        Convocatoria::findOrFail($convocatoriaId);

        return ApiResponse::ok(CalificacionService::criteriosVigentes($convocatoriaId));
    }

    public function store(
        Request $request,
        int $convocatoriaId
    ): JsonResponse {
        $convocatoria = Convocatoria::findOrFail($convocatoriaId);
        self::assertCriteriosEditables($convocatoria);

        $datos = $this->validar($request);
        $this->assertNoPasaDe100($convocatoriaId, (float) $datos['puntaje_maximo']);

        $criterio = DB::transaction(function () use ($convocatoriaId, $datos) {
            $ultimoOrden = CriterioEvaluacion::where('convocatoria_id', $convocatoriaId)
                ->where('seccion', $datos['seccion'])
                ->max('orden') ?? 0;

            $criterio = CriterioEvaluacion::create([
                'convocatoria_id' => $convocatoriaId,
                'seccion'         => $datos['seccion'],
                'nombre'          => $datos['nombre'],
                'descripcion'     => $datos['descripcion'] ?? null,
                'puntaje_maximo'  => $datos['puntaje_maximo'],
                'tipo_input'      => $datos['tipo_input'],
                'orden'           => $ultimoOrden + 1,
                'activo'          => true,
            ]);

            $this->crearOpciones($criterio, $datos['opciones'] ?? []);

            return $criterio;
        });

        return ApiResponse::created(
            $criterio->load('opciones'),
            'Criterio registrado correctamente.'
        );
    }

    public function destroy(
        int $convocatoriaId,
        int $criterioId
    ): JsonResponse {
        $convocatoria = Convocatoria::findOrFail($convocatoriaId);
        $criterio = CriterioEvaluacion::where('convocatoria_id', $convocatoriaId)
            ->where('activo', true)
            ->findOrFail($criterioId);

        self::assertCriteriosEditables($convocatoria);

        if ($criterio->calificaciones()->exists()) {
            $criterio->update(['activo' => false]);

            return ApiResponse::ok([], 'Criterio retirado. Las calificaciones hechas con él se conservan.');
        }

        $criterio->delete();

        return ApiResponse::ok([], 'Criterio eliminado.');
    }

    /** También la usa la aplicación de plantillas. */
    public static function assertCriteriosEditables(Convocatoria $convocatoria): void
    {
        if (! $convocatoria->es_contenedor_permanente && $convocatoria->estado !== EstadoConvocatoria::BORRADOR) {
            throw new ReglaNegocioException(
                'Los criterios solo se configuran mientras la convocatoria está en borrador.'
            );
        }
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'seccion'             => ['required', 'in:meritos,oposicion'],
            'nombre'              => ['required', 'string', 'max:200'],
            'descripcion'         => ['nullable', 'string'],
            'puntaje_maximo'      => ['required', 'numeric', 'min:0.5', 'max:100'],
            'tipo_input'          => ['required', 'in:radio,numero,checklist'],
            'opciones'            => ['nullable', 'array'],
            'opciones.*.etiqueta' => ['required', 'string', 'max:200'],
            'opciones.*.puntaje'  => ['required', 'numeric', 'min:0'],
        ]);

        $tipo = $datos['tipo_input'];
        $maximo = (float) $datos['puntaje_maximo'];
        $opciones = $datos['opciones'] ?? [];

        // Un radio o un checklist sin opciones no se puede calificar; un
        // número no las usa; y una opción no puede valer más que su criterio.
        if ($tipo === 'numero' && ! empty($opciones)) {
            throw ValidationException::withMessages(['opciones' => 'Un criterio numérico no lleva opciones.']);
        }
        if ($tipo !== 'numero' && empty($opciones)) {
            throw ValidationException::withMessages(['opciones' => 'Agregue al menos una opción.']);
        }
        foreach ($opciones as $i => $opcion) {
            if ((float) $opcion['puntaje'] > $maximo) {
                throw ValidationException::withMessages([
                    "opciones.{$i}.puntaje" => "Una opción no puede valer más que el criterio ({$maximo} puntos).",
                ]);
            }
        }

        return $datos;
    }

    private function assertNoPasaDe100(int $convocatoriaId, float $nuevo): void
    {
        $resto = (float) CriterioEvaluacion::where('convocatoria_id', $convocatoriaId)
            ->where('activo', true)
            ->sum('puntaje_maximo');

        if (round($resto + $nuevo, 2) > 100) {
            $disponible = round(100 - $resto, 2);
            throw ValidationException::withMessages([
                'puntaje_maximo' => "Los criterios no pueden sumar más de 100 puntos: quedan {$disponible} disponibles.",
            ]);
        }
    }

    private function crearOpciones(CriterioEvaluacion $criterio, array $opciones): void
    {
        foreach (array_values($opciones) as $i => $opcion) {
            OpcionCriterio::create([
                'criterio_id' => $criterio->id,
                'etiqueta'    => $opcion['etiqueta'],
                'puntaje'     => $opcion['puntaje'],
                'orden'       => $i + 1,
            ]);
        }
    }
}
