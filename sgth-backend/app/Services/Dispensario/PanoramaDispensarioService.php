<?php

namespace App\Services\Dispensario;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lo que el tablero del Dispensario no veía: el flujo de pacientes, enfermería,
 * los reposos médicos, la salud ocupacional y la tendencia de las atenciones.
 *
 * El tablero solo contaba consultas y despachos. Aparte de
 * `EstadisticasDispensarioService` para que ninguno de los dos crezca sin
 * medida.
 */
final class PanoramaDispensarioService
{
    /** Estados de un turno en el orden en que avanza. */
    private const ESTADOS_TURNO = ['en_espera', 'en_sala', 'en_consulta', 'atendido', 'no_presentado', 'cancelada'];

    public function __construct(
        private readonly TableroSaludOcupacionalService $saludOcupacional,
        private readonly CoberturaCertificacionService $cobertura,
    ) {}

    public function resumen(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $inicio = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->endOfDay();

        return [
            'flujo_hoy' => $this->flujoDeHoy(),
            'espera_periodo_min' => $this->esperaPromedio($inicio, $fin),
            'enfermeria' => $this->enfermeria($inicio, $fin),
            'reposos' => $this->reposos($inicio, $fin),
            'salud_ocupacional' => $this->saludOcupacional(),
            'tendencia' => $this->tendencia($fin),
        ];
    }

    /** Los turnos de hoy por estado: la saturación del día. */
    private function flujoDeHoy(): array
    {
        $porEstado = DB::table('agendas_medicas')
            ->whereNull('deleted_at')
            ->whereDate('fecha', Carbon::today())
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $estados = [];
        foreach (self::ESTADOS_TURNO as $estado) {
            $estados[$estado] = (int) $porEstado->get($estado, 0);
        }

        return [
            'por_estado' => $estados,
            'total' => (int) $porEstado->sum(),
            'espera_promedio_min' => $this->esperaPromedio(Carbon::today(), Carbon::today()->endOfDay()),
        ];
    }

    /**
     * Minutos entre que el paciente llega (se registra el turno) y se registra
     * su consulta. Es una cota superior de la espera —incluye la duración de
     * la consulta hasta que se guarda—, pero mide lo mismo cada día y sirve
     * para ver si la sala se satura. Nulo si no hubo consultas con turno.
     */
    private function esperaPromedio(Carbon $inicio, Carbon $fin): ?int
    {
        $minutos = DB::table('consultas_medicas as c')
            ->join('agendas_medicas as a', 'a.id', '=', 'c.agenda_medica_id')
            ->whereNotNull('a.registrado_en')
            ->whereBetween('c.created_at', [$inicio, $fin])
            ->whereColumn('c.created_at', '>=', 'a.registrado_en')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (c.created_at - a.registrado_en)) / 60) as minutos')
            ->value('minutos');

        return $minutos === null ? null : (int) round((float) $minutos);
    }

    /** Las atenciones de enfermería del período por servicio (sin anuladas). */
    private function enfermeria(Carbon $inicio, Carbon $fin): array
    {
        $porServicio = DB::table('atenciones_enfermeria as a')
            ->leftJoin('catalogo_servicios_enfermeria as s', 's.id', '=', 'a.catalogo_servicio_id')
            ->whereNull('a.anulado_en')
            ->whereNull('a.deleted_at')
            ->whereBetween('a.atendido_en', [$inicio, $fin])
            ->select(DB::raw("COALESCE(s.nombre, 'Sin servicio') as servicio"), DB::raw('COUNT(*) as total'))
            ->groupBy('s.nombre')
            ->orderByDesc('total')
            ->orderBy('servicio')
            ->get()
            ->map(fn ($f) => ['servicio' => $f->servicio, 'total' => (int) $f->total]);

        return [
            'total' => (int) $porServicio->sum('total'),
            'por_servicio' => $porServicio->values()->all(),
        ];
    }

    /**
     * Los certificados de reposo que empezaron en el período (sin anulados):
     * cuántos, cuántos días suman y por qué diagnósticos. Es el lado médico
     * del ausentismo que mira Talento Humano.
     */
    private function reposos(Carbon $inicio, Carbon $fin): array
    {
        $base = DB::table('certificados_medicos as r')
            ->whereNull('r.anulado_en')
            ->whereNull('r.deleted_at')
            ->whereBetween('r.fecha_inicio', [$inicio->toDateString(), $fin->toDateString()]);

        $totales = (clone $base)
            ->selectRaw('COUNT(*) as certificados, COALESCE(SUM(r.dias_reposo), 0) as dias')
            ->first();

        $diagnosticos = (clone $base)
            ->join('diagnosticos_cie10 as d', 'd.id', '=', 'r.diagnostico_cie10_id')
            ->select('d.codigo', 'd.descripcion', DB::raw('COUNT(*) as total'), DB::raw('SUM(r.dias_reposo) as dias'))
            ->groupBy('d.id', 'd.codigo', 'd.descripcion')
            ->orderByDesc('dias')
            ->orderBy('d.codigo')
            ->limit(5)
            ->get()
            ->map(fn ($f) => [
                'codigo' => $f->codigo,
                'descripcion' => $f->descripcion,
                'total' => (int) $f->total,
                'dias' => (int) $f->dias,
            ]);

        return [
            'certificados' => (int) $totales->certificados,
            'dias' => (int) $totales->dias,
            'diagnosticos' => $diagnosticos->all(),
        ];
    }

    /** Lo esencial de salud ocupacional; el detalle vive en su tablero. */
    private function saludOcupacional(): array
    {
        $bandeja = $this->saludOcupacional->bandeja();
        $cobertura = $this->cobertura->resumen();

        return [
            'por_atender' => $bandeja['pendientes'] + $bandeja['en_proceso'],
            'vencidas' => $bandeja['vencidas'],
            'retiros' => $bandeja['retiros'],
            'cobertura_vencida' => $cobertura['vencida'] ?? 0,
            'cobertura_sin_evaluacion' => $cobertura['sin_evaluacion'] ?? 0,
            'plantilla' => $cobertura['total'] ?? 0,
        ];
    }

    /** Atenciones por mes y especialidad: los doce meses que terminan en `$hasta`. */
    private function tendencia(Carbon $hasta): array
    {
        $desde = $hasta->copy()->startOfMonth()->subMonths(11);

        $filas = DB::table('consultas_medicas')
            ->whereBetween('created_at', [$desde, $hasta])
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as mes, especialidad, COUNT(*) as total")
            ->groupBy('mes', 'especialidad')
            ->get()
            ->groupBy('mes');

        $meses = [];
        for ($i = 0; $i < 12; $i++) {
            $mes = $desde->copy()->addMonths($i)->format('Y-m');
            $delMes = $filas->get($mes, collect())->pluck('total', 'especialidad');
            $meses[] = [
                'mes' => $mes,
                'medicina_general' => (int) $delMes->get('medicina_general', 0),
                'odontologia' => (int) $delMes->get('odontologia', 0),
            ];
        }

        return $meses;
    }
}
