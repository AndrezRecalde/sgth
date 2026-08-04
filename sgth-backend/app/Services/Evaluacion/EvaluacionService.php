<?php

namespace App\Services\Evaluacion;

use App\Contracts\Evaluacion\EvaluacionServiceInterface;
use App\Enums\CalificacionMrl;
use App\Enums\EstadoEvaluacion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Evaluacion\EvaluacionDesempeno;
use App\Models\Evaluacion\PlanMejora;
use App\Models\Evaluacion\ResultadoEvaluacion;
use App\Models\Expediente\Servidor;
use Illuminate\Support\Facades\DB;

final class EvaluacionService implements EvaluacionServiceInterface
{
    public function registrarResultado(int $evaluacionId, int $servidorId, array $datos, int $evaluadorId): ResultadoEvaluacion
    {
        $evaluacion = EvaluacionDesempeno::findOrFail($evaluacionId);

        if ($evaluacion->estado !== EstadoEvaluacion::EN_EVALUACION) {
            throw new ReglaNegocioException('El período de evaluación no se encuentra activo para registro de calificaciones.');
        }

        // La evaluación de desempeño (carrera administrativa) no aplica a
        // servicios profesionales (contrato civil, sin relación de
        // dependencia) ni a código de trabajo (régimen distinto, con su
        // propio instrumento) — solo a vínculos LOSEP vigentes.
        $servidor = Servidor::with('contratoVigente')->findOrFail($servidorId);

        if (!$servidor->contratoVigente?->tipo_nombramiento?->esLosep()) {
            throw new ReglaNegocioException(
                'Evaluación de desempeño no aplica a este régimen contractual.'
            );
        }

        $cuantitativa = $datos['calificacion_cuantitativa'];
        $cualitativa = $this->determinarEscalaMrl($cuantitativa);

        DB::beginTransaction();
        try {
            $resultado = ResultadoEvaluacion::updateOrCreate(
                [
                    'evaluacion_id' => $evaluacionId,
                    'servidor_id'   => $servidorId,
                ],
                [
                    'evaluador_id'              => $evaluadorId,
                    'calificacion_cuantitativa' => $cuantitativa,
                    'calificacion_cualitativa'  => $cualitativa->value,
                    'observaciones'             => $datos['observaciones'] ?? null,
                    'updated_by'                => $evaluadorId,
                ]
            );

            // Generar Plan de Mejora Automático para puntajes no excelentes
            if ($cualitativa !== CalificacionMrl::EXCELENTE && !empty($datos['brechas_identificadas'])) {
                PlanMejora::updateOrCreate(
                    ['resultado_id' => $resultado->id],
                    [
                        'brechas_identificadas' => $datos['brechas_identificadas'],
                        'acciones_mejora'       => $datos['acciones_mejora'] ?? 'Definir acciones...',
                        'fecha_cumplimiento'    => now()->addMonths(3)->toDateString(),
                        'created_by'            => $evaluadorId,
                    ]
                );
            }

            // Reglas de Sumario Administrativo (Módulo 14)
            if ($cualitativa === CalificacionMrl::INSUFICIENTE) {
                $this->dispararSumarioDisciplinario($servidorId, $evaluacion->periodo, 'Calificación Insuficiente (<= 60) en Evaluación del Desempeño.');
            } elseif ($cualitativa === CalificacionMrl::REGULAR) {
                if ($this->tieneRegularConsecutivo($servidorId, $evaluacionId)) {
                    $this->dispararSumarioDisciplinario($servidorId, $evaluacion->periodo, 'Dos calificaciones consecutivas Regulares (61-70) en Evaluación del Desempeño.');
                }
            }

            DB::commit();

            return $resultado;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function determinarEscalaMrl(float $nota): CalificacionMrl
    {
        if ($nota >= 91) return CalificacionMrl::EXCELENTE;
        if ($nota >= 81) return CalificacionMrl::MUY_BUENO;
        if ($nota >= 71) return CalificacionMrl::SATISFACTORIO;
        if ($nota >= 61) return CalificacionMrl::REGULAR;
        return CalificacionMrl::INSUFICIENTE;
    }

    private function tieneRegularConsecutivo(int $servidorId, int $evaluacionActualId): bool
    {
        // Busca el último resultado de evaluación anterior a esta
        $ultimoResultado = ResultadoEvaluacion::where('servidor_id', $servidorId)
            ->where('evaluacion_id', '<', $evaluacionActualId)
            ->orderBy('id', 'desc')
            ->first();

        return $ultimoResultado && $ultimoResultado->calificacion_cualitativa === CalificacionMrl::REGULAR;
    }

    private function dispararSumarioDisciplinario(int $servidorId, string $periodo, string $motivo): void
    {
        // Esta función asume la existencia de la tabla 'sumarios' (Módulo 14)
        // Se ejecuta una inserción directa para garantizar la integración inter-módulo en este sprint.
        DB::table('sumarios')->insert([
            'servidor_id'       => $servidorId,
            'motivo'            => $motivo,
            'estado'            => 'abierto',
            'fecha_apertura'    => now()->toDateString(),
            'notificado_sn'     => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }
}
