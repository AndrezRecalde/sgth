<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\MantenimientoServiceInterface;
use App\Enums\EstadoBien;
use App\Enums\TipoMantenimiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\CampaniaEquipo;
use App\Models\InventarioTi\CampaniaMantenimiento;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El plan de mantenimiento preventivo por campaña (fase 5.1), como la pestaña
 * «Planificador de proyectos» del Excel de TIC: un lugar, un técnico, un
 * período y los equipos, con su avance.
 *
 * - Al crearla entran los equipos principales en servicio del lugar (y de la
 *   unidad, si se acota); después se agregan o se quitan.
 * - Marcar un equipo como hecho registra un mantenimiento preventivo de verdad
 *   en su ficha. Si ese mantenimiento se anula desde la ficha, el equipo vuelve
 *   a contar como pendiente.
 * - Un equipo que no se pudo atender se omite con su motivo; al cerrar la
 *   campaña, lo pendiente queda como no realizado.
 */
final class CampaniaMantenimientoService
{
    private const RELACIONES = ['ubicacion:id,sede,piso', 'unidad:id,nombre', 'tecnico.servidor:id,nombre,apellido'];

    private const RELACIONES_BIEN = [
        'tipo:id,nombre', 'marca:id,nombre', 'ubicacion:id,sede,piso',
        'asignacionVigente.servidor:id,nombre,apellido',
    ];

    public function __construct(
        private readonly UbicacionesBienService $ubicaciones,
        private readonly MantenimientoServiceInterface $mantenimientos,
    ) {}

    /** @return Collection<int, CampaniaMantenimiento> */
    public function listar(): Collection
    {
        return CampaniaMantenimiento::with(self::RELACIONES)
            ->withCount([
                'equipos as total',
                'equipos as hechos' => fn (Builder $q) => $this->soloHechos($q),
                'equipos as omitidos' => fn (Builder $q) => $q->where('estado', CampaniaEquipo::OMITIDO),
            ])
            ->orderBy('cerrada')->orderByDesc('fecha_inicio')->orderByDesc('id')
            ->get();
    }

    public function crear(array $datos, User $user): CampaniaMantenimiento
    {
        return DB::transaction(function () use ($datos, $user) {
            $c = CampaniaMantenimiento::create([...$datos, 'created_by' => $user->id]);
            $ids = $this->ubicaciones->principalesEn($c->ubicacion_id, $c->unidad_administrativa_id)->pluck('id');
            $this->sumar($c, $ids, $user);

            return $c;
        });
    }

    public function actualizar(CampaniaMantenimiento $c, array $datos): CampaniaMantenimiento
    {
        $this->exigirAbierta($c);
        $c->update($datos);

        return $c;
    }

    /**
     * Cada equipo con lo que se hizo y el resumen. Cerrada, lo que quedó
     * pendiente se ve como no realizado.
     *
     * @return array{filas: list<array<string, mixed>>, resumen: array<string, int>}
     */
    public function detalle(CampaniaMantenimiento $c): array
    {
        $filas = $c->equipos()
            ->with([
                'bien' => fn ($q) => $q->with(self::RELACIONES_BIEN),
                'mantenimiento:id,fecha_mantenimiento,tecnico_id,anulado_en',
                'mantenimiento.tecnico.servidor:id,nombre,apellido',
                'actualizadoPor.servidor:id,nombre,apellido',
            ])
            ->get()
            ->map(fn (CampaniaEquipo $e) => $this->fila($c, $e))
            ->sortBy('codigo_institucional')
            ->values();

        $cuenta = fn (string $estado) => $filas->where('estado', $estado)->count();

        return [
            'filas'   => $filas->all(),
            'resumen' => [
                'total'         => $filas->count(),
                'hechos'        => $cuenta(CampaniaEquipo::HECHO),
                'pendientes'    => $cuenta(CampaniaEquipo::PENDIENTE),
                'omitidos'      => $cuenta(CampaniaEquipo::OMITIDO),
                'no_realizados' => $cuenta('no_realizado'),
            ],
        ];
    }

    /**
     * Suma equipos a la campaña, de cualquier lugar: los principales que no
     * están dados de baja. Los que ya estaban no se repiten.
     *
     * @param  list<int>  $bienIds
     */
    public function agregar(CampaniaMantenimiento $c, array $bienIds, User $user): int
    {
        $this->exigirAbierta($c);
        $bienes = BienInformatico::whereKey($bienIds)->get(['id', 'codigo_institucional', 'bien_padre_id', 'estado_operativo']);

        $componentes = $bienes->whereNotNull('bien_padre_id');
        if ($componentes->isNotEmpty()) {
            throw new ReglaNegocioException('Los componentes se atienden con su equipo: ' . $componentes->pluck('codigo_institucional')->join(', ') . '.');
        }
        $deBaja = $bienes->filter(fn (BienInformatico $b) => $b->estado_operativo === EstadoBien::DADO_DE_BAJA);
        if ($deBaja->isNotEmpty()) {
            throw new ReglaNegocioException('Están dados de baja: ' . $deBaja->pluck('codigo_institucional')->join(', ') . '.');
        }

        return $this->sumar($c, $bienes->pluck('id'), $user);
    }

    /** Se quita un equipo que no se atendió; uno hecho ya tiene su mantenimiento en la ficha. */
    public function quitar(CampaniaMantenimiento $c, CampaniaEquipo $e): void
    {
        $this->exigirAbierta($c);
        $this->exigirDeLaCampania($c, $e);
        if ($e->estadoEfectivo() === CampaniaEquipo::HECHO) {
            throw new ReglaNegocioException('Ese equipo ya tiene su mantenimiento: para quitarlo, anule primero el mantenimiento desde su ficha.');
        }
        $e->delete();
    }

    /**
     * Marca como hechos los equipos elegidos: a cada uno le registra un
     * mantenimiento preventivo hecho por TIC, con lo que dice la campaña (y la
     * observación, si la hay). Pueden estar pendientes u omitidos.
     *
     * @param  list<int>  $itemIds
     */
    public function realizar(CampaniaMantenimiento $c, array $itemIds, array $datos, User $user): int
    {
        $this->exigirAbierta($c);
        $fecha = Carbon::parse($datos['fecha']);
        if ($fecha->lt($c->fecha_inicio)) {
            throw new ReglaNegocioException('La fecha no puede ser anterior al inicio de la campaña (' . $c->fecha_inicio->format('d/m/Y') . ').');
        }

        $items = $this->elegidos($c, $itemIds);
        $hechos = $items->filter(fn (CampaniaEquipo $e) => $e->estadoEfectivo() === CampaniaEquipo::HECHO);
        if ($hechos->isNotEmpty()) {
            throw new ReglaNegocioException('Ya tienen su mantenimiento: ' . $hechos->map(fn (CampaniaEquipo $e) => $e->bien->codigo_institucional)->join(', ') . '.');
        }

        $descripcion = $c->descripcion . (empty($datos['observacion']) ? '' : '. ' . $datos['observacion']);

        return DB::transaction(function () use ($c, $items, $datos, $descripcion, $user) {
            foreach ($items as $e) {
                $m = $this->mantenimientos->registrar([
                    'bien_informatico_id' => $e->bien_informatico_id,
                    'tipo_mantenimiento'  => TipoMantenimiento::PREVENTIVO->value,
                    'realizado_por'       => MantenimientoBien::POR_TIC,
                    'fecha_mantenimiento' => $datos['fecha'],
                    'tecnico_id'          => $datos['tecnico_id'] ?? $c->tecnico_id,
                    'descripcion'         => mb_substr("Campaña «{$c->nombre}»: {$descripcion}", 0, 2000),
                ]);
                $e->update([
                    'estado' => CampaniaEquipo::HECHO, 'mantenimiento_bien_id' => $m->id,
                    'motivo_omision' => null, 'actualizado_por' => $user->id,
                ]);
            }

            return $items->count();
        });
    }

    /**
     * Los equipos que no se pudieron atender, con el motivo (no estaba, no lo
     * prestaron, está con el proveedor…). Siguen pudiendo marcarse como hechos.
     *
     * @param  list<int>  $itemIds
     */
    public function omitir(CampaniaMantenimiento $c, array $itemIds, string $motivo, User $user): int
    {
        $this->exigirAbierta($c);
        $items = $this->elegidos($c, $itemIds);
        $hechos = $items->filter(fn (CampaniaEquipo $e) => $e->estadoEfectivo() === CampaniaEquipo::HECHO);
        if ($hechos->isNotEmpty()) {
            throw new ReglaNegocioException('Ya tienen su mantenimiento, no se omiten: ' . $hechos->map(fn (CampaniaEquipo $e) => $e->bien->codigo_institucional)->join(', ') . '.');
        }

        CampaniaEquipo::whereKey($items->modelKeys())->update([
            'estado' => CampaniaEquipo::OMITIDO, 'mantenimiento_bien_id' => null,
            'motivo_omision' => $motivo, 'actualizado_por' => $user->id, 'updated_at' => now(),
        ]);

        return $items->count();
    }

    /** Cerrada ya no cambia; lo pendiente se ve como no realizado. */
    public function cerrar(CampaniaMantenimiento $c, User $user): CampaniaMantenimiento
    {
        $this->exigirAbierta($c);
        $c->update(['cerrada' => true, 'cerrada_por' => $user->id, 'cerrada_en' => now()]);

        return $c;
    }

    /** @return array<string, mixed> */
    public function cabecera(CampaniaMantenimiento $c): array
    {
        return [
            'id'          => $c->id,
            'nombre'      => $c->nombre,
            'descripcion' => $c->descripcion,
            'situacion'   => $c->situacion(),
            'ubicacion_id' => $c->ubicacion_id,
            'ubicacion'   => $c->ubicacion?->nombre,
            'unidad_id'   => $c->unidad_administrativa_id,
            'unidad'      => $c->unidad?->nombre,
            'tecnico_id'  => $c->tecnico_id,
            'tecnico'     => $c->tecnico?->nombre_completo,
            'fecha_inicio' => $c->fecha_inicio->toDateString(),
            'fecha_fin'   => $c->fecha_fin->toDateString(),
            'cerrada_por' => $c->cerradaPor?->nombre_completo,
            'cerrada_en'  => $c->cerrada_en?->toIso8601String(),
            'total'       => $c->total ?? null,
            'hechos'      => $c->hechos ?? null,
            'omitidos'    => $c->omitidos ?? null,
        ];
    }

    /** @param  Collection<int, mixed>  $bienIds */
    private function sumar(CampaniaMantenimiento $c, Collection $bienIds, User $user): int
    {
        $ya = $c->equipos()->pluck('bien_informatico_id');
        $nuevos = $bienIds->map(fn ($id) => (int) $id)->diff($ya)->unique()->values();
        $ahora = now();

        CampaniaEquipo::insert($nuevos->map(fn (int $id) => [
            'campania_id' => $c->id, 'bien_informatico_id' => $id, 'estado' => CampaniaEquipo::PENDIENTE,
            'actualizado_por' => $user->id, 'created_at' => $ahora, 'updated_at' => $ahora,
        ])->all());

        return $nuevos->count();
    }

    /**
     * @param  list<int>  $itemIds
     * @return Collection<int, CampaniaEquipo>
     */
    private function elegidos(CampaniaMantenimiento $c, array $itemIds): Collection
    {
        $items = $c->equipos()->whereKey($itemIds)->with('bien:id,codigo_institucional', 'mantenimiento:id,anulado_en')->get();
        if ($items->count() !== count(array_unique($itemIds))) {
            throw new ReglaNegocioException('Algunos de los equipos elegidos no son de esta campaña.');
        }

        return $items;
    }

    /** Hecho de verdad: con su mantenimiento sin anular (lo mismo que `CampaniaEquipo::estadoEfectivo`). */
    private function soloHechos(Builder $q): Builder
    {
        return $q->where('estado', CampaniaEquipo::HECHO)
            ->whereHas('mantenimiento', fn (Builder $m) => $m->whereNull('anulado_en'));
    }

    /** @return array<string, mixed> */
    private function fila(CampaniaMantenimiento $c, CampaniaEquipo $e): array
    {
        $b = $e->bien;
        $estado = $e->estadoEfectivo();
        $hecho = $estado === CampaniaEquipo::HECHO;

        return [
            'item_id'              => $e->id,
            'bien_id'              => $b->id,
            'codigo_institucional' => $b->codigo_institucional,
            'equipo'               => trim(implode(' ', array_filter([$b->tipo?->nombre, $b->marca?->nombre, $b->modelo]))),
            'custodio'             => $b->asignacionVigente?->servidor?->nombre_completo,
            'ubicacion'            => $b->ubicacion?->nombre,
            'estado'               => $c->cerrada && $estado === CampaniaEquipo::PENDIENTE ? 'no_realizado' : $estado,
            // Hecho y luego anulado desde la ficha: vuelve a pendiente y se avisa.
            'anulado'              => $e->estado === CampaniaEquipo::HECHO && ! $hecho,
            'mantenimiento_id'     => $hecho ? $e->mantenimiento_bien_id : null,
            'fecha'                => $hecho ? $e->mantenimiento->fecha_mantenimiento?->toDateString() : null,
            'tecnico'              => $hecho ? $e->mantenimiento->tecnico?->nombre_completo : null,
            'motivo_omision'       => $estado === CampaniaEquipo::OMITIDO ? $e->motivo_omision : null,
            'actualizado_por'      => $e->actualizadoPor?->nombre_completo,
            'actualizado_en'       => $e->updated_at?->toIso8601String(),
        ];
    }

    private function exigirAbierta(CampaniaMantenimiento $c): void
    {
        if ($c->cerrada) {
            throw new ReglaNegocioException('La campaña ya está cerrada: no se puede modificar.');
        }
    }

    private function exigirDeLaCampania(CampaniaMantenimiento $c, CampaniaEquipo $e): void
    {
        if ($e->campania_id !== $c->id) {
            throw new ReglaNegocioException('Ese equipo no es de esta campaña.');
        }
    }
}
