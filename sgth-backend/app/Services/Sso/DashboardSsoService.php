<?php

namespace App\Services\Sso;

use App\Contracts\Sso\SsoServiceInterface;
use App\Enums\NivelRiesgoAssist;
use App\Enums\NivelRiesgoPsicosocial;
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
        [$inicio, $fin] = PeriodoSso::rango($periodo);

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
        $porNivel = RiesgoLaboral::query()
            ->where('estado', true)
            ->selectRaw('nivel_intervencion, COUNT(*) AS total')
            ->groupBy('nivel_intervencion')
            // Sin ordenar, el orden de los niveles en el tablero era el que
            // quisiera el motor. Alfabético coincide con el de la NTP 330
            // —i, ii, iii, iv— y PostgreSQL deja los nulos al final, que es
            // donde tienen que ir los riesgos sin valorar.
            ->orderBy('nivel_intervencion')
            ->pluck('total', 'nivel_intervencion')
            ->map(fn($total) => (int) $total);

        return [
            'total_activos' => $porNivel->sum(),
            // La clave vacía son los riesgos anteriores a la matriz NTP 330,
            // que tienen el nivel en NULL. Antes salían igual, porque agrupar
            // por null en PHP también da la clave vacía.
            'por_nivel_intervencion' => $porNivel,
        ];
    }

    private function resumenAccidentes(Carbon $inicio, Carbon $fin): array
    {
        $fila = AccidenteTrabajo::query()
            ->whereBetween('fecha_accidente', [$inicio, $fin])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(CASE WHEN requirio_atencion_medica THEN 1 ELSE 0 END), 0) AS con_atencion')
            ->selectRaw('COALESCE(SUM(dias_reposo_medico), 0) AS dias_reposo')
            ->first();

        return [
            'total' => (int) $fila->total,
            'con_atencion_medica' => (int) $fila->con_atencion,
            'dias_reposo_total' => (int) $fila->dias_reposo,
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
        $campanias = EvaluacionPsicosocial::where('periodo', $periodo);
        $ids = (clone $campanias)->pluck('id');

        $respuestas = fn() => RespuestaPsicosocial::whereIn('evaluacion_psicosocial_id', $ids);

        return [
            'campanias_activas' => (clone $campanias)->where('activa', true)->count(),
            'total_respuestas' => $respuestas()->count(),
            'riesgo_alto' => $respuestas()
                ->where('nivel_riesgo_global', NivelRiesgoPsicosocial::ALTO->value)
                ->count(),
        ];
    }

    private function resumenAssist(string $periodo): array
    {
        $campanias = EvaluacionAssist::where('periodo', $periodo);
        $ids = (clone $campanias)->pluck('id');

        $respuestas = fn() => RespuestaAssist::whereIn('evaluacion_assist_id', $ids);

        return [
            'campanias_activas' => (clone $campanias)->where('activa', true)->count(),
            'total_respuestas' => $respuestas()->count(),
            'riesgo_alto' => $respuestas()
                ->where('nivel_riesgo_maximo', NivelRiesgoAssist::ALTO->value)
                ->count(),
            // «No reporta consumo» es quien contestó que no ha consumido
            // ninguna sustancia: el mapa de niveles llega vacío. Se compara
            // como jsonb porque el tipo `json` de PostgreSQL no tiene operador
            // de igualdad, y contra las dos formas de lo vacío porque el mapa
            // lo arma PHP: un array asociativo sin claves se serializa `[]`,
            // no `{}`. Quedarse con una sola contaba cero.
            'sin_consumo_reportado' => $respuestas()
                ->whereRaw("niveles_riesgo::jsonb IN ('[]'::jsonb, '{}'::jsonb)")
                ->count(),
        ];
    }

    private function resumenAusentismo(Carbon $inicio, Carbon $fin): array
    {
        // Los minutos se suman en la base: `hora_inicio` y `hora_fin` son
        // columnas `time` obligatorias, así que la resta es un intervalo y da
        // lo mismo que restarlas con Carbon una por una.
        $fila = PermisoServidor::query()
            ->whereBetween('fecha', [$inicio, $fin])
            ->where('tipo', 'enfermedad')
            ->whereNotIn('estado', ['anulado', 'pendiente'])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(DISTINCT servidor_id) AS servidores')
            ->selectRaw('COALESCE(SUM(EXTRACT(EPOCH FROM (hora_fin - hora_inicio)) / 60), 0) AS minutos')
            ->first();

        return [
            'total_permisos' => (int) $fila->total,
            'servidores_afectados' => (int) $fila->servidores,
            // 480 minutos es la jornada de ocho horas.
            'total_dias' => round(((float) $fila->minutos) / 480, 2),
        ];
    }

}
