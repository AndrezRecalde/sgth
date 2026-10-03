<?php

namespace App\Services\Dispensario\Reportes;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cómo funcionó la cola: turnos dados, atendidos, ausencias, cancelaciones,
 * asistencia y espera promedio. Por especialidad, por día o por profesional.
 *
 * La asistencia es atendidos sobre turnos no cancelados: cancelar no es
 * faltar. La espera mide desde que el turno se registra hasta que se guarda
 * la consulta —la misma cota que el Panorama—, así que incluye la duración
 * de la consulta. Agregado.
 */
final class GestionTurnosReporte extends ReporteBase
{
    private const ESPECIALIDAD = ['medicina_general' => 'Medicina general', 'odontologia' => 'Odontología'];

    public function clave(): string { return 'turnos'; }

    public function titulo(): string { return 'Gestión de turnos'; }

    public function descripcion(): string
    {
        return 'Turnos, atendidos, ausencias, cancelaciones, asistencia y espera promedio.';
    }

    public function area(): string { return 'Gestión'; }

    public function nominal(): bool { return false; }

    public function perfiles(): array
    {
        return [AlcanceReporte::ADMINISTRACION, AlcanceReporte::AUTORIDAD];
    }

    public function filtros(): array
    {
        return ['especialidad'];
    }

    public function agrupaciones(): array
    {
        return ['especialidad' => 'Especialidad', 'dia' => 'Día', 'profesional' => 'Profesional'];
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        $primera = match ($this->agrupacion($filtros)) {
            'dia'         => ['fecha', 'Fecha'],
            'profesional' => ['profesional', 'Profesional'],
            default       => ['especialidad', 'Especialidad'],
        };

        return self::columnasDe([
            $primera, ['turnos', 'Turnos'], ['atendidos', 'Atendidos'], ['no_presentados', 'No se presentaron'],
            ['cancelados', 'Cancelados'], ['abiertos', 'Sin cerrar'], ['asistencia', 'Asistencia (%)'],
            ['espera_min', 'Espera promedio (min)'],
        ]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $agrupacion = $this->agrupacion($filtros);

        $turnos = DB::table('agendas_medicas as a')
            ->leftJoin('consultas_medicas as c', function ($j) {
                $j->on('c.agenda_medica_id', '=', 'a.id')->whereNull('c.deleted_at');
            })
            ->leftJoin('users as u', 'u.id', '=', 'a.medico_id')
            ->leftJoin('servidores as ps', 'ps.id', '=', 'u.servidor_id')
            ->whereNull('a.deleted_at')
            ->whereBetween('a.fecha', [$filtros->desde->toDateString(), $filtros->hasta->toDateString()])
            ->when($filtros->especialidad, fn ($q) => $q->where('a.tipo_atencion', $filtros->especialidad))
            ->get([
                'a.id', 'a.fecha', 'a.estado', 'a.tipo_atencion', 'a.registrado_en', 'c.created_at as consulta_en',
                DB::raw("COALESCE(NULLIF(TRIM(COALESCE(ps.nombre, '') || ' ' || COALESCE(ps.apellido, '')), ''), u.usuario_ti) as profesional"),
            ])
            // Un turno con dos consultas (no debería) contaría doble.
            ->unique('id');

        return $turnos
            ->groupBy(fn ($t) => match ($agrupacion) {
                'dia'         => substr((string) $t->fecha, 0, 10),
                'profesional' => $t->profesional ?? 'Sin profesional',
                default       => self::ESPECIALIDAD[$t->tipo_atencion] ?? $t->tipo_atencion,
            })
            ->map(fn (Collection $grupo, string $clave) => [
                match ($agrupacion) { 'dia' => 'fecha', 'profesional' => 'profesional', default => 'especialidad' } => $clave,
                ...self::resumir($grupo),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    /** @return array<string, int|float|null> */
    public static function resumir(Collection $turnos): array
    {
        $atendidos  = $turnos->where('estado', 'atendido')->count();
        $cancelados = $turnos->where('estado', 'cancelada')->count();
        $validos    = $turnos->count() - $cancelados;

        $esperas = $turnos
            ->filter(fn ($t) => $t->registrado_en && $t->consulta_en && $t->consulta_en >= $t->registrado_en)
            ->map(fn ($t) => (strtotime($t->consulta_en) - strtotime($t->registrado_en)) / 60);

        return [
            'turnos'         => $turnos->count(),
            'atendidos'      => $atendidos,
            'no_presentados' => $turnos->where('estado', 'no_presentado')->count(),
            'cancelados'     => $cancelados,
            'abiertos'       => $turnos->whereIn('estado', ['en_espera', 'en_sala', 'en_consulta'])->count(),
            'asistencia'     => $validos > 0 ? round($atendidos * 100 / $validos, 1) : null,
            'espera_min'     => $esperas->isNotEmpty() ? (int) round($esperas->avg()) : null,
        ];
    }
}
