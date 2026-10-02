<?php

namespace App\Services\Sso;

use App\Enums\NivelRiesgoPsicosocial;
use App\Enums\EstadoCampaniaSso;
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
     * Es público porque es la operación con consecuencia para la persona
     * evaluada —el nivel de riesgo psicosocial que queda en su campaña— y no
     * toca la base de datos: así se prueba item por item contra las tablas de
     * la guía del MDT, que es la única forma de detectar una errata en ellas.
     *
     * @param array<int|string, int> $respuestas ítem (1-58) => puntuación (1-4)
     * @return array{0: array, 1: int, 2: NivelRiesgoPsicosocial}
     */
    public function calcularPuntajes(array $respuestas): array
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

    /**
     * La campaña del código, solo si su ventana admite respuestas.
     *
     * Comprobaba `activa` y `fecha_cierre`, nunca `fecha_apertura`: una
     * campaña creada con apertura el mes que viene se respondía hoy con su
     * enlace, y con eso el campo era decorativo. Y la comparación del cierre
     * era `isPast()` sobre una columna casteada a `date`, así que a las 00:00
     * del día de cierre ya era pasado: el último día de toda campaña era
     * inservible.
     *
     * Las tres condiciones viven ahora en `EstadoCampaniaSso`, el mismo
     * cálculo que la pantalla enseña en su columna Estado. Y el mensaje
     * distingue los dos motivos: a quien abre el enlace de una campaña
     * programada, decirle «ya fue cerrada» lo manda a reportar un error que no
     * existe.
     */
    private function campaniaAbiertaPorCodigo(string $codigoAcceso): EvaluacionPsicosocial
    {
        $campania = EvaluacionPsicosocial::where('codigo_acceso', $codigoAcceso)->first();

        if (! $campania) {
            throw ValidationException::withMessages([
                'codigo_acceso' => 'No encontramos esta evaluación. Revise el enlace que recibió.',
            ]);
        }

        $estado = EstadoCampaniaSso::desde(
            (bool) $campania->activa,
            $campania->fecha_apertura,
            $campania->fecha_cierre,
        );

        if (! $estado->admiteRespuestas()) {
            throw ValidationException::withMessages([
                'codigo_acceso' => $estado === EstadoCampaniaSso::PROGRAMADA
                    ? 'Esta evaluación abre el '.$campania->fecha_apertura->format('d/m/Y')
                        .'. Vuelva a entrar con este mismo enlace a partir de esa fecha.'
                    : 'Esta evaluación ya fue cerrada y no admite más respuestas.',
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
