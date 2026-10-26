<?php

namespace App\Services\Dispensario\Reportes;

use App\Enums\AreaExamen;
use App\Models\Dispensario\OrdenExamen;
use Illuminate\Support\Facades\DB;

/**
 * Los exámenes que se pidieron en el período (6.5 de
 * docs/diseno-examenes-medicos.md): órdenes, exámenes, cuántos volvieron,
 * cuántos esperan y cuántos se vencieron, y en cuántos días vuelve un
 * resultado. Por mes, por área del catálogo o por quien pidió la orden.
 *
 * El retorno es la mediana de días desde la solicitud hasta que el resultado
 * llega al Dispensario —se sube o se valida el del Portal—, no la fecha que
 * pone el laboratorio: lo que mide es cuánto tarda el paciente en volver. Las
 * anuladas solo cuentan en su columna. Por área, una orden con exámenes de dos
 * áreas cuenta en las dos. Es agregado: la autoridad también lo ve.
 */
final class ExamenesPedidosReporte extends ReporteBase
{
    public function clave(): string { return 'examenes_pedidos'; }

    public function titulo(): string { return 'Exámenes pedidos'; }

    public function area(): string { return 'Exámenes'; }

    public function descripcion(): string
    {
        return 'Órdenes y exámenes pedidos, los que volvieron, los que esperan o se vencieron, y los días de retorno.';
    }

    public function nominal(): bool { return false; }

    public function perfiles(): array
    {
        return [
            AlcanceReporte::ADMINISTRACION,
            AlcanceReporte::MEDICO, AlcanceReporte::ODONTOLOGO,
        ];
    }

    public function filtros(): array
    {
        return ['profesional'];
    }

    public function agrupaciones(): array
    {
        return ['mes' => 'Mes', 'area' => 'Área', 'profesional' => 'Profesional'];
    }

    public function columnas(FiltrosReporte $filtros): array
    {
        $primera = match ($this->agrupacion($filtros)) {
            'area'        => ['area', 'Área'],
            'profesional' => ['profesional', 'Pidió'],
            default       => ['mes', 'Mes'],
        };

        return self::columnasDe([
            $primera, ['ordenes', 'Órdenes'], ['examenes', 'Exámenes pedidos'], ['recibidos', 'Recibidos'],
            ['no_realizados', 'No realizados'], ['pendientes', 'Pendientes'],
            ['vencidos', 'Vencidos sin resultado'], ['retorno_dias', 'Retorno (mediana, días)'],
            ['anuladas', 'Órdenes anuladas'],
        ]);
    }

    public function filas(FiltrosReporte $filtros, AlcanceReporte $alcance): array
    {
        $agrupacion = $this->agrupacion($filtros);
        // Lo propio manda sobre el filtro, como en la producción.
        $profesional = $alcance->profesionalId ?? $filtros->profesionalId;
        $grupo = match ($agrupacion) {
            'area'        => 'e.area',
            'profesional' => 'o.solicitado_por',
            default       => "to_char(o.fecha_solicitud, 'YYYY-MM')",
        };

        // Cuándo llegó el primer resultado de cada examen: retirar uno no cuenta.
        $llegada = DB::table('resultado_orden_items as ri')
            ->join('resultados_medicos as rm', 'rm.id', '=', 'ri.resultado_medico_id')
            ->whereNull('rm.deleted_at')
            ->groupBy('ri.orden_examen_item_id')
            ->selectRaw('ri.orden_examen_item_id, MIN(rm.created_at) as llego_en');

        $vigente = "o.estado <> 'anulada'";
        $abierta = "o.estado IN ('emitida', 'parcial') AND i.estado = 'pendiente'";
        $limite = today()->subDays(OrdenExamen::DIAS_VIGENCIA)->toDateString();

        $filas = DB::table('orden_examen_items as i')
            ->join('ordenes_examen as o', 'o.id', '=', 'i.orden_examen_id')
            ->join('catalogo_examenes as e', 'e.id', '=', 'i.catalogo_examen_id')
            ->leftJoinSub($llegada, 'l', 'l.orden_examen_item_id', '=', 'i.id')
            ->whereBetween('o.fecha_solicitud', [$filtros->desde->toDateString(), $filtros->hasta->toDateString()])
            ->when($profesional, fn ($q) => $q->where('o.solicitado_por', $profesional))
            ->groupByRaw($grupo)
            ->selectRaw("{$grupo} as grupo")
            ->selectRaw("COUNT(DISTINCT o.id) FILTER (WHERE {$vigente}) as ordenes")
            ->selectRaw("COUNT(*) FILTER (WHERE {$vigente}) as examenes")
            ->selectRaw("COUNT(*) FILTER (WHERE {$vigente} AND i.estado = 'recibido') as recibidos")
            ->selectRaw("COUNT(*) FILTER (WHERE {$vigente} AND i.estado = 'no_realizado') as no_realizados")
            ->selectRaw("COUNT(*) FILTER (WHERE {$abierta} AND o.fecha_solicitud >= ?) as pendientes", [$limite])
            ->selectRaw("COUNT(*) FILTER (WHERE {$abierta} AND o.fecha_solicitud < ?) as vencidos", [$limite])
            ->selectRaw("percentile_cont(0.5) WITHIN GROUP (ORDER BY (l.llego_en::date - o.fecha_solicitud))
                FILTER (WHERE {$vigente} AND l.llego_en IS NOT NULL) as retorno")
            ->selectRaw("COUNT(DISTINCT o.id) FILTER (WHERE o.estado = 'anulada') as anuladas")
            ->get();

        $nombres = $agrupacion === 'profesional'
            ? $this->nombresDeUsuarios($filas->pluck('grupo')->all())
            : [];

        return $filas
            ->map(fn ($f) => [
                ...match ($agrupacion) {
                    'area'        => ['area' => AreaExamen::tryFrom((string) $f->grupo)?->etiqueta() ?? $f->grupo],
                    'profesional' => ['profesional' => $nombres[$f->grupo] ?? "Usuario {$f->grupo}"],
                    default       => ['mes' => self::nombreDeMes((string) $f->grupo)],
                },
                'ordenes'       => (int) $f->ordenes,
                'examenes'      => (int) $f->examenes,
                'recibidos'     => (int) $f->recibidos,
                'no_realizados' => (int) $f->no_realizados,
                'pendientes'    => (int) $f->pendientes,
                'vencidos'      => (int) $f->vencidos,
                'retorno_dias'  => $f->retorno !== null ? round((float) $f->retorno, 1) : null,
                'anuladas'      => (int) $f->anuladas,
                // Para ordenar: los meses por fecha y las áreas, en el orden de la hoja del laboratorio.
                '__orden'       => match ($agrupacion) {
                    'area'        => AreaExamen::tryFrom((string) $f->grupo)?->orden() ?? 99,
                    'profesional' => $nombres[$f->grupo] ?? '',
                    default       => $f->grupo,
                },
            ])
            ->sortBy('__orden')
            ->map(function (array $fila) {
                unset($fila['__orden']);

                return $fila;
            })
            ->values()
            ->all();
    }
}
