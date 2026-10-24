<?php

namespace App\Services\Dispensario\Reportes;

use Illuminate\Support\Facades\DB;

/**
 * Lo que el médico leyó en los resultados que llegaron en el período (6.5 de
 * docs/diseno-examenes-medicos.md): cuántos esperan su lectura, cuántos
 * salieron normales, alterados o críticos, y en cuántos días se leen. Por mes
 * o por el médico que responde por ellos (D3).
 *
 * Responde quien pidió la orden o, sin orden, quien atendió la consulta: el
 * mismo que en la bandeja y en «Mi jornada». Un resultado cuenta en el mes en
 * que llegó al Dispensario. Lo subido antes de la revisión (fase 4) quedó
 * revisado sin lectura y va aparte, sin pesar en el porcentaje. Agregado.
 */
final class LecturaResultadosReporte extends ReporteBase
{
    private const RESPONSABLE = 'COALESCE(o.solicitado_por, c.medico_id)';

    public function clave(): string { return 'examenes_lectura'; }

    public function titulo(): string { return 'Lectura de resultados'; }

    public function area(): string { return 'Exámenes'; }

    public function descripcion(): string
    {
        return 'Resultados recibidos, los que esperan lectura, los normales, alterados y críticos, y los días hasta leerlos.';
    }

    public function nominal(): bool { return false; }

    public function perfiles(): array
    {
        return [
            AlcanceReporte::ADMINISTRACION, AlcanceReporte::AUTORIDAD,
            AlcanceReporte::MEDICO, AlcanceReporte::ODONTOLOGO,
        ];
    }

    public function filtros(): array
    {
        return ['profesional'];
    }

    public function agrupaciones(): array
    {
        return ['mes' => 'Mes', 'profesional' => 'Médico que responde'];
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        $primera = $this->agrupacion($filtros) === 'profesional'
            ? ['profesional', 'Responde']
            : ['mes', 'Mes'];

        return self::columnasDe([
            $primera, ['resultados', 'Resultados recibidos'], ['del_portal', 'Enviados desde el Portal'],
            ['por_revisar', 'Por revisar'], ['normales', 'Normales'], ['alterados', 'Alterados'],
            ['criticos', 'Críticos'], ['alterados_pct', 'Alterados o críticos (%)'],
            ['sin_lectura', 'Anteriores sin lectura'], ['lectura_dias', 'Días hasta leerlo (mediana)'],
        ]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $porProfesional = $this->agrupacion($filtros) === 'profesional';
        // Lo propio manda sobre el filtro: nadie pide las lecturas de otro.
        $profesional = $alcance->profesionalId ?? $filtros->profesionalId;
        $grupo = $porProfesional ? self::RESPONSABLE : "to_char(rm.created_at, 'YYYY-MM')";

        $filas = DB::table('resultados_medicos as rm')
            ->leftJoin('ordenes_examen as o', 'o.id', '=', 'rm.orden_examen_id')
            ->leftJoin('consultas_medicas as c', 'c.id', '=', 'rm.consulta_medica_id')
            ->leftJoin('envios_resultado as ep', 'ep.resultado_medico_id', '=', 'rm.id')
            ->whereNull('rm.deleted_at')
            ->whereBetween('rm.created_at', [$filtros->desde, $filtros->hasta])
            ->when($profesional, fn ($q) => $q->whereRaw(self::RESPONSABLE . ' = ?', [$profesional]))
            ->groupByRaw($grupo)
            ->selectRaw("{$grupo} as grupo")
            ->selectRaw('COUNT(*) as resultados')
            ->selectRaw('COUNT(ep.id) as del_portal')
            ->selectRaw("COUNT(*) FILTER (WHERE rm.estado_revision = 'por_revisar') as por_revisar")
            ->selectRaw("COUNT(*) FILTER (WHERE rm.interpretacion = 'normal') as normales")
            ->selectRaw("COUNT(*) FILTER (WHERE rm.interpretacion = 'alterado') as alterados")
            ->selectRaw("COUNT(*) FILTER (WHERE rm.interpretacion = 'critico') as criticos")
            ->selectRaw("COUNT(*) FILTER (WHERE rm.estado_revision = 'revisado' AND rm.interpretacion IS NULL) as sin_lectura")
            ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY (rm.revisado_en::date - rm.created_at::date))
                FILTER (WHERE rm.interpretacion IS NOT NULL AND rm.revisado_en IS NOT NULL) as lectura')
            ->get();

        $nombres = $porProfesional ? $this->nombresDeUsuarios($filas->pluck('grupo')->all()) : [];

        return $filas
            ->map(function ($f) use ($porProfesional, $nombres) {
                $leidos = (int) $f->normales + (int) $f->alterados + (int) $f->criticos;

                return [
                    ...($porProfesional
                        ? ['profesional' => $f->grupo === null ? 'Sin responsable' : ($nombres[$f->grupo] ?? "Usuario {$f->grupo}")]
                        : ['mes' => self::nombreDeMes((string) $f->grupo)]),
                    'resultados'    => (int) $f->resultados,
                    'del_portal'    => (int) $f->del_portal,
                    'por_revisar'   => (int) $f->por_revisar,
                    'normales'      => (int) $f->normales,
                    'alterados'     => (int) $f->alterados,
                    'criticos'      => (int) $f->criticos,
                    'alterados_pct' => $leidos > 0 ? round(((int) $f->alterados + (int) $f->criticos) * 100 / $leidos, 1) : null,
                    'sin_lectura'   => (int) $f->sin_lectura,
                    'lectura_dias'  => $f->lectura !== null ? round((float) $f->lectura, 1) : null,
                    // Para ordenar: los meses por fecha, no por su nombre.
                    '__orden'       => $porProfesional ? ($nombres[$f->grupo] ?? '') : $f->grupo,
                ];
            })
            ->sortBy('__orden')
            ->map(function (array $fila) {
                unset($fila['__orden']);

                return $fila;
            })
            ->values()
            ->all();
    }
}
