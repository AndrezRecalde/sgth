<?php

namespace App\Services\Sso;

use App\Enums\NivelRiesgoPsicosocial;
use App\Models\Sso\EvaluacionPsicosocial;
use App\Models\Sso\RespuestaPsicosocial;
use App\Services\Sso\Psicosocial\CuestionarioPsicosocialData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PsicosocialService
{
    // ── Campañas ───────────────────────────────────────────────────

    public function crearCampania(array $datos): EvaluacionPsicosocial
    {
        return EvaluacionPsicosocial::create([
            'periodo' => $datos['periodo'],
            'unidad_administrativa_id' => $datos['unidad_administrativa_id'] ?? null,
            'codigo_acceso' => $this->generarCodigoAcceso(),
            'fecha_apertura' => $datos['fecha_apertura'],
            'fecha_cierre' => $datos['fecha_cierre'] ?? null,
            'activa' => true,
            'creado_por' => auth()->id(),
        ]);
    }

    public function listarCampanias(array $filtros): Collection
    {
        return EvaluacionPsicosocial::query()
            ->with('unidadAdministrativa')
            ->withCount('respuestas')
            ->when(isset($filtros['periodo']), fn($q) => $q->where('periodo', $filtros['periodo']))
            ->orderByDesc('fecha_apertura')
            ->get();
    }

    public function cerrarCampania(int $id): EvaluacionPsicosocial
    {
        $campania = EvaluacionPsicosocial::findOrFail($id);
        $campania->update(['activa' => false, 'fecha_cierre' => $campania->fecha_cierre ?? now()]);
        return $campania->fresh();
    }

    /** Cuestionario completo (preguntas + dimensiones) para renderizar el formulario público. */
    public function obtenerCuestionario(string $codigoAcceso): array
    {
        $campania = $this->campaniaAbiertaPorCodigo($codigoAcceso);

        return [
            'evaluacion' => $campania,
            'preguntas' => CuestionarioPsicosocialData::preguntas(),
            'dimensiones' => CuestionarioPsicosocialData::dimensiones(),
            'datos_generales_opciones' => CuestionarioPsicosocialData::datosGeneralesOpciones(),
        ];
    }

    // ── Respuestas anónimas ────────────────────────────────────────

    public function registrarRespuesta(string $codigoAcceso, array $datos): RespuestaPsicosocial
    {
        $campania = $this->campaniaAbiertaPorCodigo($codigoAcceso);

        $respuestas = $datos['respuestas'];
        [$puntajesDimensiones, $puntajeGlobal, $nivelGlobal] = $this->calcularPuntajes($respuestas);

        return RespuestaPsicosocial::create([
            'evaluacion_psicosocial_id' => $campania->id,
            'area_trabajo' => $datos['area_trabajo'] ?? null,
            'nivel_instruccion' => $datos['nivel_instruccion'] ?? null,
            'antiguedad' => $datos['antiguedad'] ?? null,
            'rango_edad' => $datos['rango_edad'] ?? null,
            'autoidentificacion_etnica' => $datos['autoidentificacion_etnica'] ?? null,
            'genero' => $datos['genero'] ?? null,
            'respuestas' => $respuestas,
            'puntajes_dimensiones' => $puntajesDimensiones,
            'puntaje_global' => $puntajeGlobal,
            'nivel_riesgo_global' => $nivelGlobal->value,
        ]);
    }

    /**
     * Suma los ítems de cada dimensión y subdimensión (Tabla 2), determina su nivel de riesgo
     * comparando contra la Tabla 3, y calcula el puntaje/nivel global contra la Tabla 4.
     * El puntaje global es la suma de las 8 dimensiones principales (ítems 1-58, sin
     * duplicar las subdimensiones de "otros puntos importantes", que son solo un desglose).
     *
     * @param array<int|string, int> $respuestas ítem (1-58) => puntuación (1-4)
     * @return array{0: array, 1: int, 2: NivelRiesgoPsicosocial}
     */
    private function calcularPuntajes(array $respuestas): array
    {
        $resultado = [];
        $puntajeGlobal = 0;

        foreach (CuestionarioPsicosocialData::dimensiones() as $key => $dimension) {
            $puntaje = $this->sumarItems($respuestas, $dimension['items']);
            $resultado[$key] = [
                'etiqueta' => $dimension['etiqueta'],
                'puntaje' => $puntaje,
                'nivel' => $this->determinarNivel($puntaje, $dimension['rangos'])->value,
            ];
            $puntajeGlobal += $puntaje;
        }

        foreach (CuestionarioPsicosocialData::subdimensiones() as $key => $subdimension) {
            $puntaje = $this->sumarItems($respuestas, $subdimension['items']);
            $resultado['otros_puntos_importantes']['subdimensiones'][$key] = [
                'etiqueta' => $subdimension['etiqueta'],
                'puntaje' => $puntaje,
                'nivel' => $this->determinarNivel($puntaje, $subdimension['rangos'])->value,
            ];
        }

        $nivelGlobal = $this->determinarNivel($puntajeGlobal, CuestionarioPsicosocialData::rangoGlobal());

        return [$resultado, $puntajeGlobal, $nivelGlobal];
    }

    private function sumarItems(array $respuestas, array $items): int
    {
        $suma = 0;
        foreach ($items as $numero) {
            $suma += (int) ($respuestas[$numero] ?? $respuestas[(string) $numero] ?? 0);
        }
        return $suma;
    }

    private function determinarNivel(int $puntaje, array $rangos): NivelRiesgoPsicosocial
    {
        foreach (['alto', 'medio', 'bajo'] as $nivel) {
            [$min, $max] = $rangos[$nivel];
            if ($puntaje >= $min && $puntaje <= $max) {
                return NivelRiesgoPsicosocial::from($nivel);
            }
        }

        // Puntaje fuera de rango (no debería ocurrir con datos válidos 1-4 por ítem):
        // se ubica en el extremo más cercano.
        return $puntaje < $rangos['alto'][0]
            ? NivelRiesgoPsicosocial::ALTO
            : NivelRiesgoPsicosocial::BAJO;
    }

    // ── Resultados agregados ───────────────────────────────────────

    public function resultadosAgregados(int $evaluacionId): array
    {
        $campania = EvaluacionPsicosocial::findOrFail($evaluacionId);
        $respuestas = $campania->respuestas()->get();

        $porDimension = [];
        foreach (CuestionarioPsicosocialData::dimensiones() as $key => $dimension) {
            $niveles = $respuestas->map(fn($r) => $r->puntajes_dimensiones[$key]['nivel'] ?? null)->filter();
            $porDimension[$key] = [
                'etiqueta' => $dimension['etiqueta'],
                'bajo' => $niveles->filter(fn($n) => $n === 'bajo')->count(),
                'medio' => $niveles->filter(fn($n) => $n === 'medio')->count(),
                'alto' => $niveles->filter(fn($n) => $n === 'alto')->count(),
            ];
        }

        $nivelesGlobales = $respuestas->pluck('nivel_riesgo_global');

        return [
            'evaluacion' => $campania,
            'total_respuestas' => $respuestas->count(),
            'global' => [
                'bajo' => $nivelesGlobales->filter(fn($n) => $n === 'bajo')->count(),
                'medio' => $nivelesGlobales->filter(fn($n) => $n === 'medio')->count(),
                'alto' => $nivelesGlobales->filter(fn($n) => $n === 'alto')->count(),
            ],
            'por_dimension' => $porDimension,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function campaniaAbiertaPorCodigo(string $codigoAcceso): EvaluacionPsicosocial
    {
        $campania = EvaluacionPsicosocial::where('codigo_acceso', $codigoAcceso)->first();

        if (! $campania || ! $campania->activa || ($campania->fecha_cierre && $campania->fecha_cierre->isPast())) {
            throw ValidationException::withMessages([
                'codigo_acceso' => 'Esta evaluación no está disponible o ya fue cerrada.',
            ]);
        }

        return $campania;
    }

    private function generarCodigoAcceso(): string
    {
        do {
            $codigo = strtoupper(Str::random(8));
        } while (EvaluacionPsicosocial::where('codigo_acceso', $codigo)->exists());

        return $codigo;
    }
}
