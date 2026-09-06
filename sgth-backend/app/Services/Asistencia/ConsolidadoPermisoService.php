<?php

namespace App\Services\Asistencia;

use App\Models\Asistencia\PermisoServidor;
use Illuminate\Support\Collection;

/**
 * El consolidado de permisos por servidor en un rango de fechas.
 *
 * Existía tres veces, copiado y pegado en `consolidado()`, `exportarExcel()` y
 * `exportarPdf()` del controlador: la misma consulta, la misma agrupación y la
 * misma aritmética, con las claves del arreglo cambiadas al final para cada
 * formato. Unas 160 líneas repetidas donde cualquier corrección había que
 * acertarla tres veces — y de hecho el filtro de estados estaba mal en las
 * tres a la vez.
 *
 * La agregación además se hacía en PHP: se traían todos los permisos del rango
 * a memoria con su servidor y su unidad, se agrupaban con `groupBy()` y se
 * sumaban recorriendo la colección. Para un año de una institución entera eso
 * es traerse decenas de miles de filas para escupir unos cientos. Postgres
 * agrupa y suma en una consulta.
 */
class ConsolidadoPermisoService
{
    /** Jornada completa, en minutos. */
    private const MINUTOS_JORNADA = 480;

    /**
     * Los estados que cuentan como tiempo de permiso concedido.
     *
     * Se dice en positivo y no como «todos menos anulado y pendiente». Con
     * aquella forma, `falta_injustificada` y `rechazado` entraban en el
     * informe: una falta injustificada es exactamente el permiso que NO se
     * concedió, y sumaba horas.
     *
     * @var list<string>
     */
    private const ESTADOS_CONCEDIDOS = ['activo', 'validado_trabajo_social'];

    /**
     * @return array{consolidado: Collection<int, array<string, mixed>>, totales: array<string, mixed>}
     */
    public function generar(string $fechaInicio, string $fechaFin, string $tipo): array
    {
        $consolidado = $this->filas($fechaInicio, $fechaFin, $tipo);

        return [
            'consolidado' => $consolidado,
            'totales' => [
                'total_permisos' => $consolidado->sum('total_permisos'),
                'total_minutos'  => $consolidado->sum('total_minutos'),
                'total_dias'     => round($consolidado->sum('total_dias'), 2),
            ],
        ];
    }

    /**
     * Una fila por servidor, ya sumada por la base.
     *
     * `hora_fin - hora_inicio` entre dos columnas `time` da un intervalo en
     * PostgreSQL; `EXTRACT(EPOCH FROM ...)` lo pasa a segundos. Es la misma
     * cuenta que hacía el PHP, solo que sin traerse las filas.
     *
     * La unidad es la del servidor hoy, no la que quedó grabada en el permiso.
     * Es lo que el informe ha mostrado siempre y cambiarlo movería los totales
     * históricos de sitio; conviene decidirlo con Talento Humano y no de paso.
     *
     * El `join` no mira el borrado en blando del servidor ni el de la unidad, a
     * diferencia de las relaciones que se usaban antes. Los totales son los
     * mismos —el permiso existió y las horas se tomaron—, pero la fila de un
     * servidor dado de baja deja de salir con el nombre y la cédula en blanco.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function filas(string $fechaInicio, string $fechaFin, string $tipo): Collection
    {
        return PermisoServidor::query()
            ->join('servidores', 'servidores.id', '=', 'permisos_servidor.servidor_id')
            ->leftJoin(
                'unidades_administrativas',
                'unidades_administrativas.id', '=', 'servidores.unidad_administrativa_id'
            )
            ->whereBetween('permisos_servidor.fecha', [$fechaInicio, $fechaFin])
            ->where('permisos_servidor.tipo', $tipo)
            ->whereIn('permisos_servidor.estado', self::ESTADOS_CONCEDIDOS)
            ->groupBy(
                'servidores.id',
                'servidores.cedula',
                'servidores.apellido',
                'servidores.segundo_apellido',
                'servidores.nombre',
                'servidores.segundo_nombre',
                'unidades_administrativas.nombre',
            )
            ->orderBy('servidores.apellido')
            ->orderBy('servidores.nombre')
            ->selectRaw(<<<'SQL'
                servidores.id                    as servidor_id,
                servidores.cedula                as cedula,
                servidores.apellido              as apellido,
                servidores.segundo_apellido      as segundo_apellido,
                servidores.nombre                as nombre,
                servidores.segundo_nombre        as segundo_nombre,
                unidades_administrativas.nombre  as unidad,
                COUNT(*)                         as total_permisos,
                COALESCE(SUM(
                    EXTRACT(EPOCH FROM (permisos_servidor.hora_fin - permisos_servidor.hora_inicio)) / 60
                ), 0)                            as total_minutos
            SQL)
            ->get()
            ->map(fn ($fila) => $this->componer($fila));
    }

    /**
     * @return array<string, mixed>
     */
    private function componer(object $fila): array
    {
        $minutos = (int) round((float) $fila->total_minutos);

        $nombre = mb_strtoupper(implode(' ', array_filter([
            $fila->apellido,
            $fila->segundo_apellido,
            $fila->nombre,
            $fila->segundo_nombre,
        ])), 'UTF-8');

        return [
            'servidor_id'     => (int) $fila->servidor_id,
            'servidor_nombre' => $nombre,
            'cedula'          => $fila->cedula,
            'unidad'          => $fila->unidad ?? '—',
            'total_permisos'  => (int) $fila->total_permisos,
            'total_minutos'   => $minutos,
            'tiempo_total'    => sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60),
            'total_dias'      => round($minutos / self::MINUTOS_JORNADA, 2),
        ];
    }

    /**
     * Las mismas filas con los encabezados que espera la hoja de cálculo.
     *
     * @param  Collection<int, array<string, mixed>>  $consolidado
     * @return list<array<string, mixed>>
     */
    public function paraExcel(Collection $consolidado): array
    {
        return $consolidado->map(fn (array $fila) => [
            'Cedula'         => $fila['cedula'],
            'Servidor'       => $fila['servidor_nombre'],
            'Unidad'         => $fila['unidad'],
            'Total Permisos' => $fila['total_permisos'],
            'Total Minutos'  => $fila['total_minutos'],
            'Tiempo Total'   => $fila['tiempo_total'],
            'Total Dias'     => $fila['total_dias'],
        ])->values()->all();
    }

    /**
     * Y con las que espera la plantilla del PDF, que llama `nombre` a lo que
     * en el JSON es `servidor_nombre`.
     *
     * @param  Collection<int, array<string, mixed>>  $consolidado
     * @return Collection<int, array<string, mixed>>
     */
    public function paraPdf(Collection $consolidado): Collection
    {
        return $consolidado->map(fn (array $fila) => [
            'cedula'         => $fila['cedula'],
            'nombre'         => $fila['servidor_nombre'],
            'unidad'         => $fila['unidad'],
            'total_permisos' => $fila['total_permisos'],
            'total_minutos'  => $fila['total_minutos'],
            'tiempo_total'   => $fila['tiempo_total'],
            'total_dias'     => $fila['total_dias'],
        ])->values();
    }
}
