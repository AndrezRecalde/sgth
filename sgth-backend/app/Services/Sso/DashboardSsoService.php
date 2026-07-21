<?php

namespace App\Services\Sso;

use App\Contracts\Sso\SsoServiceInterface;
use App\Enums\NivelRiesgoAssist;
use App\Enums\NivelRiesgoPsicosocial;
use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\EppEntrega;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\EvaluacionAssist;
use App\Models\Sso\EvaluacionPsicosocial;
use App\Models\Sso\RespuestaAssist;
use App\Models\Sso\RespuestaPsicosocial;
use App\Models\Sso\RiesgoLaboral;
use Carbon\Carbon;

final class DashboardSsoService
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
        private readonly CumplimientoService $cumplimientoService,
        private readonly ProgramaDrogasService $programaDrogasService,
    ) {}

    public function resumen(string $periodo, ?int $unidadAdministrativaId = null): array
    {
        [$inicio, $fin] = $this->rangoPeriodo($periodo);

        return [
            'periodo' => $periodo,
            'riesgos' => $this->resumenRiesgos(),
            'accidentes' => $this->resumenAccidentes($inicio, $fin),
            'epp' => $this->resumenEpp($inicio, $fin),
            'indicadores_reactivos' => $this->ssoService->calcularIndicadoresMrl($periodo, $unidadAdministrativaId),
            'indicadores_proactivos' => $this->ssoService->calcularIndicadoresProactivos($periodo, $unidadAdministrativaId),
            'cumplimiento' => $this->cumplimientoService->listaVerificacion($periodo)['totales'],
            'psicosocial' => $this->resumenPsicosocial($periodo),
            'assist' => $this->resumenAssist($periodo),
            'programa_drogas' => $this->programaDrogasService->listaSeguimiento($periodo)['totales'],
            'ausentismo' => $this->resumenAusentismo($inicio, $fin),
        ];
    }

    private function resumenRiesgos(): array
    {
        $riesgos = RiesgoLaboral::where('estado', true)->get();

        return [
            'total_activos' => $riesgos->count(),
            'por_nivel_intervencion' => $riesgos
                ->groupBy(fn(RiesgoLaboral $r) => $r->nivel_intervencion?->value)
                ->map->count(),
        ];
    }

    private function resumenAccidentes(Carbon $inicio, Carbon $fin): array
    {
        $accidentes = AccidenteTrabajo::whereBetween('fecha_accidente', [$inicio, $fin])->get();

        return [
            'total' => $accidentes->count(),
            'con_atencion_medica' => $accidentes->where('requirio_atencion_medica', true)->count(),
            'dias_reposo_total' => (int) $accidentes->sum('dias_reposo_medico'),
        ];
    }

    private function resumenEpp(Carbon $inicio, Carbon $fin): array
    {
        return [
            'equipos_activos' => EquipoProteccion::where('estado', true)->count(),
            'entregas_periodo' => EppEntrega::whereBetween('fecha_entrega', [$inicio, $fin])->count(),
        ];
    }

    private function resumenPsicosocial(string $periodo): array
    {
        $evaluaciones = EvaluacionPsicosocial::where('periodo', $periodo)->withCount('respuestas')->get();
        $respuestas = collect();
        if ($evaluaciones->isNotEmpty()) {
            $respuestas = RespuestaPsicosocial::whereIn(
                'evaluacion_psicosocial_id',
                $evaluaciones->pluck('id'),
            )->get();
        }

        return [
            'campanias_activas' => $evaluaciones->where('activa', true)->count(),
            'total_respuestas' => $respuestas->count(),
            'riesgo_alto' => $respuestas->where('nivel_riesgo_global', NivelRiesgoPsicosocial::ALTO->value)->count(),
        ];
    }

    private function resumenAssist(string $periodo): array
    {
        $evaluaciones = EvaluacionAssist::where('periodo', $periodo)->get();
        $respuestas = collect();
        if ($evaluaciones->isNotEmpty()) {
            $respuestas = RespuestaAssist::whereIn(
                'evaluacion_assist_id',
                $evaluaciones->pluck('id'),
            )->get();
        }

        return [
            'campanias_activas' => $evaluaciones->where('activa', true)->count(),
            'total_respuestas' => $respuestas->count(),
            'riesgo_alto' => $respuestas->where('nivel_riesgo_maximo', NivelRiesgoAssist::ALTO->value)->count(),
            'sin_consumo_reportado' => $respuestas->filter(fn($r) => empty($r->niveles_riesgo))->count(),
        ];
    }

    private function resumenAusentismo(Carbon $inicio, Carbon $fin): array
    {
        $permisos = PermisoServidor::whereBetween('fecha', [$inicio, $fin])
            ->where('tipo', 'enfermedad')
            ->whereNotIn('estado', ['anulado', 'pendiente'])
            ->get();

        $totalMinutos = $permisos->sum(function (PermisoServidor $p) {
            return Carbon::parse($p->hora_inicio)->diffInMinutes(Carbon::parse($p->hora_fin));
        });

        return [
            'total_permisos' => $permisos->count(),
            'servidores_afectados' => $permisos->pluck('servidor_id')->unique()->count(),
            'total_dias' => round($totalMinutos / 480, 2),
        ];
    }

    private function rangoPeriodo(string $periodo): array
    {
        if (preg_match('/^\d{4}$/', $periodo)) {
            $inicio = Carbon::createFromDate((int) $periodo, 1, 1)->startOfYear();
            return [$inicio, $inicio->copy()->endOfYear()];
        }

        if (preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            $inicio = Carbon::createFromFormat('Y-m-d', "{$periodo}-01")->startOfMonth();
            return [$inicio, $inicio->copy()->endOfMonth()];
        }

        throw new ReglaNegocioException('El período debe tener el formato AAAA o AAAA-MM.');
    }
}
