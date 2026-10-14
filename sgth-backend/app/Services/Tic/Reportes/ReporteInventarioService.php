<?php

namespace App\Services\Tic\Reportes;

use App\Enums\EstadoBien;
use App\Models\InventarioTi\BienInformatico;
use Illuminate\Database\Eloquent\Builder;

/**
 * El inventario de equipos agrupado por unidad o por ubicación (fase 4.2),
 * como la tabla dinámica «Dashboar» del Excel de TIC. Son los equipos
 * principales en servicio: los componentes van con su equipo y los dados de
 * baja quedan fuera.
 *
 * La unidad es la de quien tiene el equipo; si nadie lo tiene, la del propio
 * equipo (igual que el filtro del listado y el tablero).
 */
final class ReporteInventarioService
{
    /**
     * @param  array{agrupar?: string, tipo_bien_id?: int|null, unidad_administrativa_id?: int|null, ubicacion_id?: int|null}  $filtros
     * @return array{agrupado_por: string, total: int, grupos: list<array{nombre: string, total: int, equipos: list<array<string, mixed>>}>}
     */
    public function generar(array $filtros): array
    {
        $porUbicacion = ($filtros['agrupar'] ?? 'unidad') === 'ubicacion';

        $equipos = BienInformatico::with([
            'tipo:id,nombre', 'subtipo:id,nombre', 'marca:id,nombre', 'ubicacion:id,sede,piso', 'unidadAdministrativa:id,nombre',
            'asignacionVigente.servidor:id,nombre,apellido,unidad_administrativa_id', 'asignacionVigente.servidor.unidadAdministrativa:id,nombre',
            'asignacionVigente.usuario:id,nombre,apellido',
        ])
            ->whereNull('bien_padre_id')
            ->where('estado_operativo', '<>', EstadoBien::DADO_DE_BAJA->value)
            ->when($filtros['tipo_bien_id'] ?? null, fn (Builder $q, $tipo) => $q->where('tipo_bien_id', $tipo))
            ->when($filtros['ubicacion_id'] ?? null, fn (Builder $q, $u) => $q->where('ubicacion_id', $u))
            ->orderBy('codigo_institucional')
            ->get()
            ->map(fn (BienInformatico $b) => $this->fila($b))
            ->when($filtros['unidad_administrativa_id'] ?? null, fn ($c, $u) => $c->where('unidad_id', (int) $u));

        $grupos = $equipos
            ->groupBy(fn (array $e) => ($porUbicacion ? $e['ubicacion'] : $e['unidad']) ?? '')
            ->map(fn ($filas, string $nombre) => [
                'nombre'  => $nombre !== '' ? $nombre : ($porUbicacion ? 'Sin ubicación' : 'Sin unidad'),
                'total'   => $filas->count(),
                'equipos' => $filas->values()->all(),
            ])
            // Por nombre, y los que no tienen al final.
            ->sortBy(fn (array $g, string $clave) => [$clave === '' ? 1 : 0, $g['nombre']])
            ->values()->all();

        return ['agrupado_por' => $porUbicacion ? 'ubicacion' : 'unidad', 'total' => $equipos->count(), 'grupos' => $grupos];
    }

    /** @return array<string, mixed> */
    private function fila(BienInformatico $b): array
    {
        $vigente = $b->asignacionVigente;
        $unidad = $vigente?->servidor?->unidadAdministrativa ?? ($vigente ? null : $b->unidadAdministrativa);

        return [
            'id'                   => $b->id,
            'codigo_institucional' => $b->codigo_institucional,
            'tipo'                 => trim(($b->tipo?->nombre ?? '') . ($b->subtipo ? " ({$b->subtipo->nombre})" : '')),
            'marca'                => $b->marca?->nombre,
            'modelo'               => $b->modelo,
            'numero_serie'         => $b->numero_serie,
            'estado'               => $b->estado_operativo->etiqueta(),
            'condicion'            => $b->condicion_fisica?->etiqueta(),
            'custodio'             => $vigente?->servidor?->nombre_completo,
            'usuario'              => $vigente?->usuario?->nombre_completo,
            'unidad_id'            => $unidad?->id,
            'unidad'               => $unidad?->nombre,
            'ubicacion'            => $b->ubicacion ? trim($b->ubicacion->sede . ($b->ubicacion->piso ? " · {$b->ubicacion->piso}" : '')) : null,
            'fecha_compra'         => $b->fecha_compra?->toDateString(),
        ];
    }
}
