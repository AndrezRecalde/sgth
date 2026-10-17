<?php

namespace App\Services\InventarioTi;

use App\Enums\EstadoBien;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MovimientoUbicacionBien;
use App\Models\InventarioTi\Ubicacion;
use App\Models\InventarioTi\UbicacionPorUnidad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dónde está cada equipo (decidido con TIC el 2026-10-13). La ubicación es del
 * equipo y no de la unidad: una nueva administración puede cambiar de piso a
 * las unidades. Cada unidad tiene una ubicación sugerida, que solo se propone;
 * cuando una unidad se muda, la mudanza en lote mueve sus equipos. Cada cambio
 * queda en el historial del equipo, y los componentes van con su equipo.
 */
final class UbicacionesBienService
{
    /** Cambia la ubicación del equipo y de los componentes que estaban con él, y lo anota. */
    public function mover(BienInformatico $bien, ?int $nueva, string $motivo, ?string $detalle = null): void
    {
        $anterior = $bien->ubicacion_id === null ? null : (int) $bien->ubicacion_id;
        $nueva = $nueva === null ? null : (int) $nueva;
        if ($anterior === $nueva) {
            return;
        }

        $componentes = $bien->componentes()
            ->when($anterior, fn (Builder $q) => $q->where('ubicacion_id', $anterior), fn (Builder $q) => $q->whereNull('ubicacion_id'))
            ->get();

        foreach ([$bien, ...$componentes] as $equipo) {
            $equipo->update(['ubicacion_id' => $nueva, 'updated_by' => auth()->id()]);
            MovimientoUbicacionBien::create([
                'bien_informatico_id'   => $equipo->id,
                'ubicacion_anterior_id' => $anterior,
                'ubicacion_nueva_id'    => $nueva,
                'motivo'                => $motivo,
                'detalle'               => $equipo->id === $bien->id ? $detalle : "Con su equipo {$bien->codigo_institucional}.",
                'registrado_por'        => auth()->id(),
            ]);
        }
    }

    /**
     * Los equipos principales en servicio que el sistema tiene en una ubicación
     * y, si se acota, de una unidad: la de quien lo tiene o, sin custodio, la
     * del equipo. Es lo que se espera encontrar en una constatación (5.2) y lo
     * que entra en una campaña de preventivo (5.1).
     *
     * @return Builder<BienInformatico>
     */
    public function principalesEn(?int $ubicacionId, ?int $unidadId = null): Builder
    {
        return BienInformatico::whereNull('bien_padre_id')
            ->where('estado_operativo', '<>', EstadoBien::DADO_DE_BAJA->value)
            // Sin lugar, de todas partes: la campaña del proveedor recorre todo su contrato (5.1b).
            ->when($ubicacionId, fn (Builder $q) => $q->where('ubicacion_id', $ubicacionId))
            ->when($unidadId, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereHas('asignacionVigente.servidor', fn (Builder $s) => $s->where('unidad_administrativa_id', $unidadId))
                ->orWhere(fn (Builder $sin) => $sin->whereDoesntHave('asignacionVigente')->where('unidad_administrativa_id', $unidadId))))
            ->orderBy('codigo_institucional');
    }

    /** @return list<array{unidad_id: int, unidad: string, ubicacion_id: ?int, ubicacion: ?string}> */
    public function porUnidad(): array
    {
        $sugeridas = UbicacionPorUnidad::with('ubicacion')->get()->keyBy('unidad_administrativa_id');

        return UnidadAdministrativa::where('estado', true)->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (UnidadAdministrativa $u) => [
                'unidad_id'    => $u->id,
                'unidad'       => $u->nombre,
                'ubicacion_id' => $sugeridas[$u->id]->ubicacion_id ?? null,
                'ubicacion'    => isset($sugeridas[$u->id]) ? $sugeridas[$u->id]->ubicacion?->nombre : null,
            ])->values()->all();
    }

    public function fijar(int $unidadId, ?int $ubicacionId): void
    {
        $ubicacionId === null
            ? UbicacionPorUnidad::where('unidad_administrativa_id', $unidadId)->delete()
            : UbicacionPorUnidad::updateOrCreate(['unidad_administrativa_id' => $unidadId], ['ubicacion_id' => $ubicacionId, 'updated_by' => auth()->id()]);
    }

    /** La sugerida de la unidad, o de la unidad del servidor. */
    public function sugerida(?int $unidadId, ?int $servidorId = null): ?Ubicacion
    {
        $unidadId ??= $servidorId ? Servidor::whereKey($servidorId)->value('unidad_administrativa_id') : null;

        return $unidadId ? UbicacionPorUnidad::with('ubicacion')->where('unidad_administrativa_id', $unidadId)->first()?->ubicacion : null;
    }

    /**
     * Los equipos de una unidad que están en `desde` pasan a `hacia`. Son los
     * principales en servicio cuya unidad es la de la mudanza: la de quien los
     * tiene o, si nadie los tiene, la del equipo. Los que estaban en otro
     * sitio —prestados, en la bodega— no se tocan. Sin `aplicar`, solo dice
     * cuáles se moverían. Al aplicarla, `hacia` queda como sugerida.
     *
     * @return array{total: int, equipos: list<array<string, mixed>>, aplicada: bool}
     */
    public function mudanza(int $unidadId, ?int $desdeId, int $haciaId, bool $aplicar): array
    {
        if ($desdeId === $haciaId) {
            throw new ReglaNegocioException('La ubicación de destino es la misma que la de origen.');
        }

        $equipos = BienInformatico::with('tipo:id,nombre', 'marca:id,nombre', 'asignacionVigente.servidor:id,nombre,apellido')
            ->whereNull('bien_padre_id')
            ->where('estado_operativo', '<>', EstadoBien::DADO_DE_BAJA->value)
            ->where(fn (Builder $q) => $q
                ->whereHas('asignacionVigente.servidor', fn (Builder $s) => $s->where('unidad_administrativa_id', $unidadId))
                ->orWhere(fn (Builder $sin) => $sin->whereDoesntHave('asignacionVigente')->where('unidad_administrativa_id', $unidadId)))
            ->when($desdeId, fn (Builder $q) => $q->where('ubicacion_id', $desdeId), fn (Builder $q) => $q->whereNull('ubicacion_id'))
            ->orderBy('codigo_institucional')
            ->get();

        if ($aplicar) {
            if ($equipos->isEmpty()) {
                throw new ReglaNegocioException('No hay equipos de esa unidad en la ubicación de origen.');
            }

            $unidad = UnidadAdministrativa::findOrFail($unidadId)->nombre;
            $desde = $desdeId ? Ubicacion::find($desdeId)?->nombre : 'sin ubicación';
            $hacia = Ubicacion::findOrFail($haciaId)->nombre;

            DB::transaction(function () use ($equipos, $haciaId, $unidadId, $unidad, $desde, $hacia) {
                foreach ($equipos as $bien) {
                    $this->mover($bien, $haciaId, 'mudanza', "Mudanza de {$unidad}: de {$desde} a {$hacia}.");
                }
                $this->fijar($unidadId, $haciaId);
            });
        }

        return [
            'total'    => $equipos->count(),
            'aplicada' => $aplicar,
            'equipos'  => $equipos->map(fn (BienInformatico $b) => [
                'id'                   => $b->id,
                'codigo_institucional' => $b->codigo_institucional,
                'equipo'               => trim(($b->tipo?->nombre ?? '') . ' ' . ($b->marca?->nombre ?? '') . ' ' . ($b->modelo ?? '')),
                'custodio'             => $b->asignacionVigente?->servidor?->nombre_completo,
            ])->values()->all(),
        ];
    }
}
