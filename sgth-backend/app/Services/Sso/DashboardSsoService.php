<?php

namespace App\Services\Sso;

use App\Contracts\Sso\SsoServiceInterface;
use App\Enums\AlcanceIndicadorSso;
use App\Enums\EstadoPermiso;
use App\Enums\NivelRiesgoAssist;
use App\Enums\NivelRiesgoPsicosocial;
use App\Enums\TipoPermiso;
use App\Models\Sso\AccidenteTrabajo;
use App\Models\Sso\EppEntrega;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\EvaluacionAssist;
use App\Models\Sso\EvaluacionPsicosocial;
use App\Models\Sso\RespuestaAssist;
use App\Models\Sso\RespuestaPsicosocial;
use App\Models\Sso\RiesgoLaboral;
use App\Services\Asistencia\JornadaLaboral;
use App\Services\Asistencia\ReposoMedicoAusentismo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class DashboardSsoService
{
    public function __construct(
        private readonly SsoServiceInterface $ssoService,
        private readonly CumplimientoService $cumplimientoService,
        private readonly ProgramaDrogasService $programaDrogasService,
    ) {}

    /**
     * El resumen del período, con el ALCANCE de cada bloque declarado.
     *
     * El método recibía la unidad administrativa y se la pasaba a dos de los
     * nueve bloques. Los otros siete la ignoraban en silencio y el resumen se
     * devolvía igual, así que pedir el tablero de una dirección daba siete
     * cifras institucionales presentadas como si fueran de esa dirección.
     *
     * Ahora la filtran los seis que pueden —riesgos por `puestos`, accidentes
     * y ausentismo por `servidores`, las entregas de EPP por `servidores`, y
     * las dos campañas de tamizaje, que tienen unidad propia— y los tres que
     * no pueden lo dicen: el catálogo de equipos activos, la normativa legal y
     * las actividades del programa de drogas no se registran por unidad.
     *
     * Nada de esto cambia una cifra hoy, porque la pantalla todavía no ofrece
     * elegir unidad y entonces el alcance es institucional en todo. Lo que
     * cambia es que cuando lo ofrezca, las cifras serán ciertas.
     */
    public function resumen(string $periodo, ?int $unidadAdministrativaId = null): array
    {
        [$inicio, $fin] = PeriodoSso::rango($periodo);

        $deLaUnidad = AlcanceIndicadorSso::segunUnidad($unidadAdministrativaId)->comoRespuesta();
        $institucional = fn(string $motivo) => AlcanceIndicadorSso::INSTITUCIONAL->comoRespuesta(
            $unidadAdministrativaId !== null ? $motivo : null,
        );

        return [
            'periodo' => $periodo,
            'unidad_administrativa_id' => $unidadAdministrativaId,
            'riesgos' => $this->resumenRiesgos($unidadAdministrativaId),
            'accidentes' => $this->resumenAccidentes($inicio, $fin, $unidadAdministrativaId),
            'epp' => $this->resumenEpp($inicio, $fin, $unidadAdministrativaId),
            'indicadores_reactivos' => $this->ssoService->calcularIndicadoresMrl($periodo, $unidadAdministrativaId),
            'indicadores_proactivos' => $this->ssoService->calcularIndicadoresProactivos($periodo, $unidadAdministrativaId),
            'cumplimiento' => $this->cumplimientoService->listaVerificacion($periodo)['totales'],
            'psicosocial' => $this->resumenPsicosocial($periodo, $unidadAdministrativaId),
            'assist' => $this->resumenAssist($periodo, $unidadAdministrativaId),
            'programa_drogas' => $this->programaDrogasService->listaSeguimiento($periodo)['totales'],
            'ausentismo' => $this->resumenAusentismo($inicio, $fin, $unidadAdministrativaId),
            'alcances' => [
                'riesgos' => $deLaUnidad,
                'accidentes' => $deLaUnidad,
                'epp' => $institucional(
                    'Los equipos activos son el catálogo institucional; las entregas sí '
                        . 'corresponden a la unidad consultada.',
                ),
                'cumplimiento' => $institucional(
                    'La normativa legal aplica a toda la institución, no por unidad.',
                ),
                'psicosocial' => $deLaUnidad,
                'assist' => $deLaUnidad,
                'programa_drogas' => $institucional(
                    'Las actividades del programa de prevención no se registran por unidad.',
                ),
                'ausentismo' => $deLaUnidad,
            ],
        ];
    }

    private function resumenRiesgos(?int $unidadAdministrativaId = null): array
    {
        $porNivel = RiesgoLaboral::query()
            ->where('estado', true)
            // Los riesgos cuelgan de un puesto, y el puesto de una unidad.
            ->when(
                $unidadAdministrativaId,
                fn($q) => $q->whereHas(
                    'puesto',
                    fn($sq) => $sq->where('unidad_administrativa_id', $unidadAdministrativaId),
                ),
            )
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

    private function resumenAccidentes(Carbon $inicio, Carbon $fin, ?int $unidadAdministrativaId = null): array
    {
        $fila = AccidenteTrabajo::query()
            ->whereBetween('fecha_accidente', [$inicio, $fin])
            // Por el servidor, igual que `calcularIndicadoresMrl`, para que el
            // conteo del tablero y el del índice reactivo no se separen.
            ->when(
                $unidadAdministrativaId,
                fn($q) => $q->whereHas(
                    'servidor',
                    fn($sq) => $sq->where('unidad_administrativa_id', $unidadAdministrativaId),
                ),
            )
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

    private function resumenEpp(Carbon $inicio, Carbon $fin, ?int $unidadAdministrativaId = null): array
    {
        return [
            // El catálogo es institucional por naturaleza: un casco existe para
            // toda la institución, no para una dirección. Por eso el bloque
            // declara alcance institucional aunque las entregas sí filtren.
            'equipos_activos' => EquipoProteccion::where('estado', true)->count(),
            'entregas_periodo' => EppEntrega::query()
                ->whereBetween('fecha_entrega', [$inicio, $fin])
                ->when(
                    $unidadAdministrativaId,
                    fn($q) => $q->whereHas(
                        'servidor',
                        fn($sq) => $sq->where('unidad_administrativa_id', $unidadAdministrativaId),
                    ),
                )
                ->count(),
        ];
    }

    private function resumenPsicosocial(string $periodo, ?int $unidadAdministrativaId = null): array
    {
        // Las campañas tienen unidad propia: una de toda la institución lleva
        // la columna en NULL, y esa no es de ninguna unidad en particular.
        $campanias = EvaluacionPsicosocial::where('periodo', $periodo)
            ->when(
                $unidadAdministrativaId,
                fn($q) => $q->where('unidad_administrativa_id', $unidadAdministrativaId),
            );
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

    private function resumenAssist(string $periodo, ?int $unidadAdministrativaId = null): array
    {
        $campanias = EvaluacionAssist::where('periodo', $periodo)
            ->when(
                $unidadAdministrativaId,
                fn($q) => $q->where('unidad_administrativa_id', $unidadAdministrativaId),
            );
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

    private function resumenAusentismo(Carbon $inicio, Carbon $fin, ?int $unidadAdministrativaId = null): array
    {
        // Los minutos se suman en la base: `hora_inicio` y `hora_fin` son
        // columnas `time` obligatorias, así que la resta es un intervalo y da
        // lo mismo que restarlas con Carbon una por una.
        //
        // El filtro de estados es el MISMO que usa el consolidado de permisos
        // de Asistencia, que es de donde sale la pantalla de Ausentismo de este
        // módulo. Aquí estaba escrito en negativo —«todos menos anulado y
        // pendiente»—, y así entraban `rechazado` y `falta_injustificada`: dos
        // permisos que no se concedieron sumando días de ausencia. El tablero y
        // la pantalla de Ausentismo daban cifras distintas del mismo período, y
        // la del tablero era la más alta. Ver `EstadoPermiso::concedidos()`.
        //
        // Y los reposos médicos aprobados, que desde el 2026-10-08 son
        // certificados y no permisos: en días calendario, como en el
        // consolidado (`ReposoMedicoAusentismo`).
        $ausencias = DB::table('permisos_servidor')
            ->whereNull('deleted_at')
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->where('tipo', TipoPermiso::ENFERMEDAD->value)
            ->whereIn('estado', EstadoPermiso::concedidos())
            ->selectRaw('servidor_id, fecha')
            ->selectRaw('EXTRACT(EPOCH FROM (hora_fin - hora_inicio)) / 60 AS minutos')
            ->unionAll(ReposoMedicoAusentismo::filasEntre($inicio->toDateString(), $fin->toDateString()));

        $fila = DB::query()
            ->fromSub($ausencias, 'ausencias')
            ->when(
                $unidadAdministrativaId,
                fn($q) => $q->join('servidores', 'servidores.id', '=', 'ausencias.servidor_id')
                    ->where('servidores.unidad_administrativa_id', $unidadAdministrativaId),
            )
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(DISTINCT ausencias.servidor_id) AS servidores')
            ->selectRaw('COALESCE(SUM(ausencias.minutos), 0) AS minutos')
            ->first();

        return [
            'total_permisos' => (int) $fila->total,
            'servidores_afectados' => (int) $fila->servidores,
            // La jornada vive en `JornadaLaboral`; aquí se presenta con dos
            // decimales y no con cuatro, que es lo que pide un consolidado.
            'total_dias' => JornadaLaboral::aDias((float) $fila->minutos, 2),
        ];
    }

}
