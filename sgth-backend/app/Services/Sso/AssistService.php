<?php

namespace App\Services\Sso;

use App\Enums\NivelRiesgoAssist;
use App\Enums\SustanciaAssist;
use App\Models\Sso\EvaluacionAssist;
use App\Models\Sso\RespuestaAssist;
use App\Services\Sso\Assist\CuestionarioAssistData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssistService
{
    // ── Campañas ───────────────────────────────────────────────────

    public function crearCampania(array $datos): EvaluacionAssist
    {
        return EvaluacionAssist::create([
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
        return EvaluacionAssist::query()
            ->with('unidadAdministrativa')
            ->withCount('respuestas')
            ->when(isset($filtros['periodo']), fn($q) => $q->where('periodo', $filtros['periodo']))
            ->orderByDesc('fecha_apertura')
            ->get();
    }

    public function cerrarCampania(int $id): EvaluacionAssist
    {
        $campania = EvaluacionAssist::findOrFail($id);
        $campania->update(['activa' => false, 'fecha_cierre' => $campania->fecha_cierre ?? now()]);
        return $campania->fresh();
    }

    /** Cuestionario completo (sustancias + preguntas + opciones) para renderizar el formulario público. */
    public function obtenerCuestionario(string $codigoAcceso): array
    {
        $campania = $this->campaniaAbiertaPorCodigo($codigoAcceso);

        return [
            'evaluacion' => $campania,
            'sustancias' => CuestionarioAssistData::sustancias(),
            'preguntas' => CuestionarioAssistData::preguntas(),
            'pregunta_inyectable' => CuestionarioAssistData::preguntaInyectable(),
            'opciones_frecuencia_3m' => CuestionarioAssistData::opcionesFrecuencia3m(),
            'opciones_frecuencia_vida' => CuestionarioAssistData::opcionesFrecuenciaVida(),
        ];
    }

    // ── Respuestas anónimas ────────────────────────────────────────

    public function registrarRespuesta(string $codigoAcceso, array $datos): RespuestaAssist
    {
        $campania = $this->campaniaAbiertaPorCodigo($codigoAcceso);

        $puntajes = [];
        $niveles = [];
        $nivelMaximo = NivelRiesgoAssist::BAJO;

        foreach ($datos['sustancias'] as $codigoSustancia => $respuestasSustancia) {
            $sustancia = SustanciaAssist::from($codigoSustancia);
            $puntaje = $this->calcularPuntajeSustancia($sustancia, $respuestasSustancia);
            $nivel = $this->determinarNivel($sustancia, $puntaje);

            $puntajes[$codigoSustancia] = $puntaje;
            $niveles[$codigoSustancia] = $nivel->value;

            if ($this->ordenNivel($nivel) > $this->ordenNivel($nivelMaximo)) {
                $nivelMaximo = $nivel;
            }
        }

        return RespuestaAssist::create([
            'evaluacion_assist_id' => $campania->id,
            'respuestas' => $datos['sustancias'],
            'puntajes' => $puntajes,
            'niveles_riesgo' => $niveles,
            'nivel_riesgo_maximo' => $nivelMaximo->value,
            'uso_inyectable' => $datos['uso_inyectable'] ?? 'no_nunca',
        ]);
    }

    /**
     * Suma las puntuaciones de las preguntas P2 a P7 (manual Cap. 13). Si P2 = 'nunca'
     * (no consumida en los últimos 3 meses), P3-P5 no se preguntan y no suman. P5 no
     * aplica a tabaco. P1 y P8 no cuentan para el puntaje.
     */
    private function calcularPuntajeSustancia(SustanciaAssist $sustancia, array $respuestas): int
    {
        $puntuaciones = CuestionarioAssistData::puntuaciones();
        $puntaje = 0;

        $p2 = $respuestas['p2'] ?? 'nunca';
        $puntaje += $puntuaciones['p2'][$p2] ?? 0;

        if ($p2 !== 'nunca') {
            $puntaje += $puntuaciones['p3'][$respuestas['p3'] ?? 'nunca'] ?? 0;
            $puntaje += $puntuaciones['p4'][$respuestas['p4'] ?? 'nunca'] ?? 0;
            if ($sustancia->incluyePregunta5()) {
                $puntaje += $puntuaciones['p5'][$respuestas['p5'] ?? 'nunca'] ?? 0;
            }
        }

        $puntaje += $puntuaciones['p6'][$respuestas['p6'] ?? 'no_nunca'] ?? 0;
        $puntaje += $puntuaciones['p7'][$respuestas['p7'] ?? 'no_nunca'] ?? 0;

        return $puntaje;
    }

    private function determinarNivel(SustanciaAssist $sustancia, int $puntaje): NivelRiesgoAssist
    {
        $cortes = CuestionarioAssistData::puntosCorte($sustancia);

        foreach (['bajo', 'moderado', 'alto'] as $nivel) {
            [$min, $max] = $cortes[$nivel];
            if ($puntaje >= $min && $puntaje <= $max) {
                return NivelRiesgoAssist::from($nivel);
            }
        }

        return NivelRiesgoAssist::ALTO;
    }

    private function ordenNivel(NivelRiesgoAssist $nivel): int
    {
        return match ($nivel) {
            NivelRiesgoAssist::BAJO => 0,
            NivelRiesgoAssist::MODERADO => 1,
            NivelRiesgoAssist::ALTO => 2,
        };
    }

    // ── Resultados agregados ───────────────────────────────────────

    public function resultadosAgregados(int $evaluacionId): array
    {
        $campania = EvaluacionAssist::findOrFail($evaluacionId);
        $respuestas = $campania->respuestas()->get();

        $porSustancia = [];
        foreach (CuestionarioAssistData::sustancias() as $key => $info) {
            $niveles = $respuestas->map(fn($r) => $r->niveles_riesgo[$key] ?? null)->filter();
            $porSustancia[$key] = [
                'etiqueta' => $info['etiqueta'],
                'total_consumieron' => $niveles->count(),
                'bajo' => $niveles->filter(fn($n) => $n === 'bajo')->count(),
                'moderado' => $niveles->filter(fn($n) => $n === 'moderado')->count(),
                'alto' => $niveles->filter(fn($n) => $n === 'alto')->count(),
            ];
        }

        return [
            'evaluacion' => $campania,
            'total_respuestas' => $respuestas->count(),
            'sin_consumo_reportado' => $respuestas->filter(fn($r) => empty($r->niveles_riesgo))->count(),
            'riesgo_alto_alguna_sustancia' => $respuestas->where('nivel_riesgo_maximo', 'alto')->count(),
            'uso_inyectable_reciente' => $respuestas->where('uso_inyectable', 'si_ultimos_3m')->count(),
            'por_sustancia' => $porSustancia,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function campaniaAbiertaPorCodigo(string $codigoAcceso): EvaluacionAssist
    {
        $campania = EvaluacionAssist::where('codigo_acceso', $codigoAcceso)->first();

        if (! $campania || ! $campania->activa || ($campania->fecha_cierre && $campania->fecha_cierre->isPast())) {
            throw ValidationException::withMessages([
                'codigo_acceso' => 'Este tamizaje no está disponible o ya fue cerrado.',
            ]);
        }

        return $campania;
    }

    private function generarCodigoAcceso(): string
    {
        do {
            $codigo = strtoupper(Str::random(8));
        } while (EvaluacionAssist::where('codigo_acceso', $codigo)->exists());

        return $codigo;
    }
}
