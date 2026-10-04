<?php

namespace App\Services\Dispensario\Reportes;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo que hizo cada profesional en el período: consultas, procedimientos,
 * servicios de enfermería, triajes, signos vitales del FEMO, certificados y
 * recetas. Agrupado por profesional o por día.
 *
 * Por día es el informe mensual de actividades de cada uno; por profesional,
 * la comparación que hace la jefatura. Es agregado: no lleva pacientes, así
 * que la autoridad también lo ve. Lo anulado no cuenta.
 */
final class ProduccionReporte extends ReporteBase
{
    /** Los indicadores, clave => título de la columna. */
    private const INDICADORES = [
        'consultas_medicina'    => 'Consultas de medicina',
        'consultas_odontologia' => 'Consultas de odontología',
        'primera_vez'           => 'Primera vez',
        'procedimientos'        => 'Procedimientos odontológicos',
        'servicios_enfermeria'  => 'Servicios de enfermería',
        'triajes'               => 'Triajes',
        'signos_sso'            => 'Signos vitales SSO',
        'certificados'          => 'Certificados médicos',
        'dias_reposo'           => 'Días de reposo',
        'recetas'               => 'Recetas',
    ];

    public function clave(): string { return 'produccion'; }

    public function titulo(): string { return 'Producción por profesional'; }

    public function descripcion(): string
    {
        return 'Consultas, procedimientos, servicios y triajes de cada profesional, o día por día.';
    }

    public function nominal(): bool { return false; }

    public function perfiles(): array
    {
        return [
            AlcanceReporte::ADMINISTRACION, AlcanceReporte::AUTORIDAD,
            AlcanceReporte::MEDICO, AlcanceReporte::ODONTOLOGO, AlcanceReporte::ENFERMERIA,
        ];
    }

    public function filtros(): array
    {
        return ['profesional'];
    }

    public function agrupaciones(): array
    {
        return ['profesional' => 'Profesional', 'dia' => 'Día'];
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        $primera = $this->agrupacion($filtros) === 'dia'
            ? ['clave' => 'fecha', 'titulo' => 'Fecha']
            : ['clave' => 'profesional', 'titulo' => 'Profesional'];

        return [
            $primera,
            ...array_map(fn ($clave, $titulo) => ['clave' => $clave, 'titulo' => $titulo],
                array_keys(self::INDICADORES), self::INDICADORES),
            ['clave' => 'total', 'titulo' => 'Total de atenciones'],
        ];
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $profesional = $alcance->profesionalId ?? $filtros->profesionalId;
        $desde = $filtros->desde;
        $hasta = $filtros->hasta;

        // Cada fuente devuelve (actor, día, indicador, cantidad).
        $fuentes = collect([
            $this->contar(DB::table('consultas_medicas')->whereNull('deleted_at')
                ->whereBetween('fecha_consulta', [$desde->toDateString(), $hasta->toDateString()]),
                'medico_id', 'fecha_consulta', [
                    'consultas_medicina'    => "COUNT(*) FILTER (WHERE especialidad = 'medicina_general')",
                    'consultas_odontologia' => "COUNT(*) FILTER (WHERE especialidad = 'odontologia')",
                    'primera_vez'           => "COUNT(*) FILTER (WHERE tipo_atencion = 'primera_vez')",
                ], $profesional),
            $this->contar(DB::table('odontograma_procedimientos')->whereNull('deleted_at')->whereNull('anulado_en')
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]),
                'realizado_por', 'fecha', ['procedimientos' => 'COUNT(*)'], $profesional),
            $this->contar(DB::table('atenciones_enfermeria')->whereNull('deleted_at')->whereNull('anulado_en')
                ->whereBetween('atendido_en', [$desde, $hasta]),
                'enfermera_id', 'atendido_en', ['servicios_enfermeria' => 'COUNT(*)'], $profesional),
            $this->contar(DB::table('triajes')->whereBetween('registrado_en', [$desde, $hasta]),
                'enfermera_id', 'registrado_en', ['triajes' => 'COUNT(*)'], $profesional),
            $this->contar(DB::table('solicitud_constantes_vitales')->whereBetween('registrado_en', [$desde, $hasta]),
                'enfermera_id', 'registrado_en', ['signos_sso' => 'COUNT(*)'], $profesional),
            $this->contar(DB::table('certificados_medicos')->whereNull('deleted_at')->whereNull('anulado_en')
                ->whereBetween('created_at', [$desde, $hasta]),
                'emitido_por', 'created_at', ['certificados' => 'COUNT(*)', 'dias_reposo' => 'COALESCE(SUM(dias_reposo), 0)'], $profesional),
            $this->contar(DB::table('recetas_medicas as r')
                ->join('consultas_medicas as c', 'c.id', '=', 'r.consulta_medica_id')
                ->whereNull('r.deleted_at')->whereNull('r.anulado_en')
                ->whereBetween('r.fecha_emision', [$desde->toDateString(), $hasta->toDateString()]),
                'c.medico_id', 'r.fecha_emision', ['recetas' => 'COUNT(*)'], $profesional),
        ]);

        $registros = $fuentes->flatMap(fn (Builder $q) => $q->get())->values();

        return $this->agrupacion($filtros) === 'dia'
            ? $this->porDia($registros)
            : $this->porProfesional($registros);
    }

    /**
     * Agrupa una fuente por quién y qué día, con sus indicadores.
     *
     * @param array<string, string> $indicadores clave => expresión de agregado
     */
    private function contar(Builder $query, string $actor, string $fecha, array $indicadores, ?int $profesional): Builder
    {
        $selects = ["{$actor} as actor", "({$fecha})::date as dia"];
        foreach ($indicadores as $clave => $expresion) {
            $selects[] = "{$expresion} as {$clave}";
        }

        // Lo propio manda sobre el filtro: nadie pide la producción de otro
        // poniendo su id en la URL.
        return $query->selectRaw(implode(', ', $selects))
            ->whereNotNull($actor)
            ->when($profesional, fn (Builder $q) => $q->where($actor, $profesional))
            ->groupByRaw("{$actor}, ({$fecha})::date");
    }

    private function porProfesional(Collection $registros): array
    {
        $nombres = $this->nombres($registros->pluck('actor')->unique()->all());

        return $registros->groupBy('actor')
            ->map(fn (Collection $grupo, $actor) => [
                'profesional' => $nombres[$actor] ?? "Usuario {$actor}",
                ...$this->sumar($grupo),
            ])
            ->sortBy('profesional')
            ->values()
            ->all();
    }

    private function porDia(Collection $registros): array
    {
        return $registros->groupBy(fn ($r) => substr((string) $r->dia, 0, 10))
            ->map(fn (Collection $grupo, $dia) => ['fecha' => $dia, ...$this->sumar($grupo)])
            ->sortBy('fecha')
            ->values()
            ->all();
    }

    /** @return array<string, int> */
    private function sumar(Collection $grupo): array
    {
        $fila = [];
        foreach (array_keys(self::INDICADORES) as $clave) {
            $fila[$clave] = (int) $grupo->sum(fn ($r) => $r->{$clave} ?? 0);
        }

        // Atenciones a personas: no suma la primera vez (ya está en las
        // consultas), ni los días de reposo, ni las recetas y certificados,
        // que salen de una consulta ya contada.
        $fila['total'] = $fila['consultas_medicina'] + $fila['consultas_odontologia']
            + $fila['servicios_enfermeria'] + $fila['triajes'] + $fila['signos_sso'];

        return $fila;
    }

    /** @return array<int, string> */
    private function nombres(array $usuarios): array
    {
        return DB::table('users as u')
            ->leftJoin('servidores as s', 's.id', '=', 'u.servidor_id')
            ->whereIn('u.id', $usuarios)
            ->selectRaw("u.id, COALESCE(NULLIF(TRIM(COALESCE(s.nombre, '') || ' ' || COALESCE(s.apellido, '')), ''), u.usuario_ti) as nombre")
            ->pluck('nombre', 'id')
            ->all();
    }
}
