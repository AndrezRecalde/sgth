<?php

namespace App\Services\Dispensario;

use App\Enums\EstadoCoberturaCertificacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * La cobertura de las evaluaciones médicas ocupacionales de la plantilla.
 *
 * El módulo sabía listar solicitudes; esto da vuelta al eje y lista
 * **servidores**. La diferencia no es cosmética: una solicitud solo existe
 * cuando alguien se acordó de pedirla, así que quien nunca tuvo ninguna no
 * aparecía en ninguna de las tres pantallas, y es exactamente a quien hay que
 * mandar a evaluar.
 */
final class CoberturaCertificacionService
{
    /**
     * Cada cuántos años se repite la evaluación periódica. Lo fijó la UATH el
     * 2026-09-26.
     *
     * Vive también en `aptitudMedica.ts`, que calcula lo mismo para una ficha
     * ya cargada en el navegador. Son dos copias a propósito: el panel del
     * expediente no consulta este endpoint. Si el plazo cambia, se cambia en
     * los dos sitios.
     */
    public const ANIOS_ENTRE_EVALUACIONES = 2;

    /**
     * Con cuánta antelación se avisa de un vencimiento. Noventa días es el
     * margen con el que Talento Humano alcanza a lanzar el lote, coordinar la
     * agenda del Dispensario y que el servidor acuda.
     */
    public const DIAS_AVISO_VENCIMIENTO = 90;

    /**
     * @return array{datos: LengthAwarePaginator, resumen: array<string, int>}
     */
    public function listar(array $filtros): array
    {
        $datos = $this->filtradaPorEstado($filtros)
            // `nombre_completo` no desempata: dos homónimos alternarían de
            // página. La cédula es única y estable.
            ->orderByRaw($this->ordenPorUrgencia())
            ->orderBy('cedula')
            ->paginate($filtros['per_page'] ?? 15);

        return [
            'datos' => $datos,
            'resumen' => $this->resumen($filtros),
        ];
    }

    /** Las filas tal cual, sin paginar, para el Excel. */
    public function exportar(array $filtros): \Illuminate\Support\Collection
    {
        return $this->filtradaPorEstado($filtros)
            ->orderBy('unidad')
            ->orderBy('cedula')
            ->get();
    }

    /**
     * `estado_cobertura` es un alias del SELECT, y PostgreSQL no admite alias
     * en el WHERE: para filtrar por él hay que envolver la consulta. En el
     * ORDER BY sí se admite, de ahí que el orden no necesite envoltura.
     */
    private function filtradaPorEstado(array $filtros): Builder
    {
        return DB::query()
            ->fromSub($this->consulta($filtros), 'cobertura')
            ->when(
                isset($filtros['estado_cobertura']),
                fn ($q) => $q->where('estado_cobertura', $filtros['estado_cobertura'])
            );
    }

    /**
     * El semáforo. No aplica `estado_cobertura`: si al pulsar «Vencidas» el
     * resumen se recalculara sobre lo filtrado, las otras tres cifras caerían
     * a cero y el tablero dejaría de poder leerse de un golpe.
     */
    public function resumen(array $filtros = []): array
    {
        $conteos = DB::query()
            ->fromSub($this->consulta($filtros), 'cobertura')
            ->selectRaw('estado_cobertura, count(*) as total')
            ->groupBy('estado_cobertura')
            ->pluck('total', 'estado_cobertura');

        $resumen = ['total' => (int) $conteos->sum()];

        foreach (EstadoCoberturaCertificacion::cases() as $estado) {
            $resumen[$estado->value] = (int) ($conteos[$estado->value] ?? 0);
        }

        return $resumen;
    }

    /**
     * Una fila por servidor activo, con su última evaluación completada.
     *
     * La última se elige por `max(id)` entre las completadas: `created_at` es
     * `timestamp(0)` y dos solicitudes del mismo segundo no se podrían
     * desempatar por fecha.
     */
    private function consulta(array $filtros): Builder
    {
        $ultimas = DB::table('solicitudes_certificacion_medica')
            ->selectRaw('servidor_id, max(id) as solicitud_id')
            ->where('estado', 'completada')
            ->whereNotNull('servidor_id')
            ->groupBy('servidor_id');

        // La solicitud viva (pendiente o en proceso), si la hay: una fila
        // vencida que ya tiene solicitud en curso no hay que volver a pedirla.
        $activas = DB::table('solicitudes_certificacion_medica')
            ->selectRaw('servidor_id, max(id) as solicitud_id')
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->whereNotNull('servidor_id')
            ->groupBy('servidor_id');

        $anios = self::ANIOS_ENTRE_EVALUACIONES;
        $aviso = self::DIAS_AVISO_VENCIMIENTO;

        // La fecha que manda es la del acto médico; si la solicitud se cerró
        // sin ficha, la de la propia solicitud. Es el mismo orden de
        // preferencia que usa `aptitudVigente()` en el frontend.
        $fechaEvaluacion = "coalesce(ficha.fecha_evaluacion, ultima.created_at::date)";
        $vence = "($fechaEvaluacion + interval '$anios years')::date";

        return DB::table('servidores')
            ->leftJoinSub($ultimas, 'ult', 'ult.servidor_id', '=', 'servidores.id')
            ->leftJoin(
                'solicitudes_certificacion_medica as ultima',
                'ultima.id', '=', 'ult.solicitud_id'
            )
            ->leftJoin('fichas_salud_ocupacional as ficha', function ($join) {
                $join->on('ficha.id', '=', 'ultima.ficha_femo_id')
                    ->whereNull('ficha.deleted_at');
            })
            ->leftJoinSub($activas, 'act', 'act.servidor_id', '=', 'servidores.id')
            ->leftJoin(
                'solicitudes_certificacion_medica as activa',
                'activa.id', '=', 'act.solicitud_id'
            )
            ->leftJoin(
                'unidades_administrativas as unidad',
                'unidad.id', '=', 'servidores.unidad_administrativa_id'
            )
            ->leftJoin('puestos', 'puestos.id', '=', 'servidores.puesto_id')
            ->leftJoin('cargos', 'cargos.id', '=', 'puestos.cargo_id')
            ->where('servidores.estado', true)
            ->whereNull('servidores.deleted_at')
            ->when(
                isset($filtros['unidad_administrativa_id']),
                fn ($q) => $q->where(
                    'servidores.unidad_administrativa_id',
                    $filtros['unidad_administrativa_id']
                )
            )
            ->when(
                isset($filtros['buscar']),
                fn ($q) => $q->where(function ($sq) use ($filtros) {
                    $texto = '%'.mb_strtolower($filtros['buscar']).'%';
                    $sq->whereRaw(
                        "lower(servidores.nombre || ' ' || servidores.apellido) like ?", [$texto]
                    )->orWhere('servidores.cedula', 'like', $texto);
                })
            )
            ->selectRaw(<<<SQL
                servidores.id as servidor_id,
                servidores.cedula,
                trim(servidores.nombre || ' ' || servidores.apellido) as nombre_completo,
                unidad.nombre as unidad,
                cargos.nombre as cargo,
                ultima.id as ultima_solicitud_id,
                ultima.dictamen as ultimo_dictamen,
                -- Sin ficha no hay acto médico firmado y no se puede emitir el
                -- certificado de aptitud: la fila lo tiene que saber para no
                -- ofrecer una descarga que devolvería un 422.
                ficha.id as ultima_ficha_id,
                ficha.restricciones,
                $fechaEvaluacion as fecha_evaluacion,
                case when ultima.id is null then null else $vence end as vence_el,
                activa.id as solicitud_activa_id,
                activa.estado as solicitud_activa_estado,
                activa.fecha_limite as solicitud_activa_fecha_limite,
                case
                    when ultima.id is null then 'sin_evaluacion'
                    when $vence < current_date then 'vencida'
                    when $vence <= current_date + interval '$aviso days' then 'por_vencer'
                    else 'al_dia'
                end as estado_cobertura
            SQL);
    }

    /**
     * Primero lo que hay que perseguir. Sin esto el tablero abría por orden de
     * cédula y los vencidos quedaban repartidos por las veinte páginas.
     */
    private function ordenPorUrgencia(): string
    {
        return "case estado_cobertura
            when 'vencida' then 1
            when 'sin_evaluacion' then 2
            when 'por_vencer' then 3
            else 4
        end";
    }
}
