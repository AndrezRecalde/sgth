<?php

namespace App\Services\InventarioTi;

use App\Contracts\InventarioTi\ConsumibleServiceInterface;
use App\Enums\TipoMovimientoConsumible;
use App\Exceptions\ReglaNegocioException;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Consumible;
use App\Models\InventarioTi\ConsumibleModelo;
use App\Models\InventarioTi\MovimientoConsumible;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El catálogo de consumibles de TIC (fase 5.4a): tóner, tinta, cartuchos,
 * cintas, unidades de imagen, kits y repuestos informáticos, cada uno con su
 * tipo [P9], su código de bodega [P8] y los modelos de equipo a los que sirve [P1].
 *
 * - Cada consumible dice si lo guarda TIC [P4]. Si lo guarda, tiene saldo y
 *   puede tener un stock mínimo; si lo despacha Bodega, no.
 * - No se borra: lo que ya no se compra se desactiva y conserva su historia.
 *   Para desactivarlo, o para pasarlo a Bodega, su saldo en TIC debe estar en
 *   cero: si no, ese material quedaría fuera de toda cuenta.
 */
final class ConsumibleService implements ConsumibleServiceInterface
{
    private const RELACIONES = ['modelos.marca:id,nombre'];

    public function listar(array $filtros): Collection
    {
        $bien = ! empty($filtros['bien_informatico_id'])
            ? BienInformatico::find($filtros['bien_informatico_id'], ['id', 'marca_id', 'modelo'])
            : null;

        $consumibles = Consumible::with(self::RELACIONES)->conSaldo()
            ->when(empty($filtros['incluir_inactivos']), fn (Builder $q) => $q->where('activo', true))
            ->when($filtros['tipo'] ?? null, fn (Builder $q, $t) => $q->where('tipo', $t))
            ->when(isset($filtros['guarda_tic']), fn (Builder $q) => $q->where('guarda_tic', (bool) $filtros['guarda_tic']))
            ->when($filtros['search'] ?? null, fn (Builder $q, $s) => $q->where(fn (Builder $w) => $w
                ->where('descripcion', 'ilike', "%{$s}%")
                ->orWhere('codigo_bodega', 'ilike', "%{$s}%")))
            ->when($bien, fn (Builder $q) => $q->compatiblesCon($bien->marca_id, $bien->modelo))
            ->when(! $bien && ! empty($filtros['marca_id']), fn (Builder $q) => empty($filtros['modelo'])
                ? $q->whereHas('modelos', fn (Builder $m) => $m->where('marca_id', $filtros['marca_id']))
                : $q->compatiblesCon((int) $filtros['marca_id'], $filtros['modelo']))
            ->orderBy('descripcion')->orderBy('id')
            ->get();

        $filas = $consumibles->map(fn (Consumible $c) => $this->presentar($c));

        return empty($filtros['bajo_minimo']) ? $filas->values() : $filas->where('bajo_minimo', true)->values();
    }

    public function detalle(Consumible $consumible): array
    {
        $consumible->load([...self::RELACIONES, 'creadoPor.servidor:id,nombre,apellido']);

        return [
            ...$this->presentar($consumible),
            'modelos' => $consumible->modelos->map(function (ConsumibleModelo $m) {
                $equipos = $m->equiposQueCoinciden()
                    ->with('ubicacion:id,sede,piso', 'asignacionVigente.servidor:id,nombre,apellido')
                    ->orderBy('codigo_institucional')
                    ->get(['id', 'codigo_institucional', 'modelo', 'ubicacion_id', 'estado_operativo']);

                return [
                    ...$this->modelo($m),
                    'equipos' => $equipos->map(fn (BienInformatico $b) => [
                        'id'                   => $b->id,
                        'codigo_institucional' => $b->codigo_institucional,
                        'ubicacion'            => $b->ubicacion?->nombre,
                        'custodio'             => $b->asignacionVigente?->servidor?->nombre_completo,
                    ])->all(),
                ];
            })->all(),
            'creado_por' => $consumible->creadoPor?->nombre_completo,
            'creado_en'  => $consumible->created_at?->toIso8601String(),
        ];
    }

    public function crear(array $datos, User $user): Consumible
    {
        return DB::transaction(function () use ($datos, $user) {
            $consumible = Consumible::create([
                ...$this->cabecera($datos),
                'activo'     => true,
                'created_by' => $user->id,
            ]);
            $this->sincronizarModelos($consumible, $datos['modelos'] ?? []);

            return $consumible;
        });
    }

    public function actualizar(Consumible $consumible, array $datos, User $user): Consumible
    {
        return DB::transaction(function () use ($consumible, $datos, $user) {
            $consumible = Consumible::lockForUpdate()->findOrFail($consumible->id);
            $cabecera = $this->cabecera($datos);
            $activo = (bool) ($datos['activo'] ?? $consumible->activo);
            $saldo = $consumible->saldoActual();

            if ($consumible->guarda_tic && ! $cabecera['guarda_tic'] && $saldo !== 0) {
                throw new ReglaNegocioException("Para que lo despache Bodega, su saldo en TIC debe estar en cero (hoy: {$saldo}). Entréguelo o ajústelo primero.");
            }
            if ($consumible->activo && ! $activo && $saldo) {
                throw new ReglaNegocioException("Para desactivarlo, su saldo debe estar en cero (hoy: {$saldo}). Entréguelo o ajústelo primero.");
            }

            $consumible->update([...$cabecera, 'activo' => $activo, 'updated_by' => $user->id]);
            if (array_key_exists('modelos', $datos)) {
                $this->sincronizarModelos($consumible, $datos['modelos'] ?? []);
            }

            return $consumible;
        });
    }

    public function agregarModelo(Consumible $consumible, int $marcaId, string $modelo): ConsumibleModelo
    {
        $normalizado = ConsumibleModelo::normalizar($modelo);
        if ($consumible->modelos()->where('marca_id', $marcaId)->where('modelo_normalizado', $normalizado)->exists()) {
            throw new ReglaNegocioException('Ya es compatible con ese modelo.');
        }

        return $consumible->modelos()->create([
            'marca_id' => $marcaId, 'modelo' => trim($modelo), 'modelo_normalizado' => $normalizado,
        ])->load('marca:id,nombre');
    }

    public function quitarModelo(Consumible $consumible, ConsumibleModelo $modelo): void
    {
        if ($modelo->consumible_id !== $consumible->id) {
            throw new ReglaNegocioException('Ese modelo no es de este consumible.');
        }
        $modelo->delete();
    }

    public function delEquipo(BienInformatico $bien): array
    {
        $compatibles = Consumible::with(self::RELACIONES)->conSaldo()
            ->where('activo', true)
            ->compatiblesCon($bien->marca_id, $bien->modelo)
            ->orderBy('descripcion')
            ->get();

        $entregas = MovimientoConsumible::with('consumible:id,descripcion,tipo,color', 'retira:id,nombre,segundo_nombre,apellido,segundo_apellido')
            ->where('bien_informatico_id', $bien->id)
            ->where('tipo', TipoMovimientoConsumible::ENTREGA)
            ->vivos()
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit(10)
            ->get();
        $ultima = $entregas->first()?->fecha;

        return [
            'compatibles'       => $compatibles->map(fn (Consumible $c) => $this->presentar($c))->all(),
            'entregas'          => $entregas->map(fn (MovimientoConsumible $m) => [
                'id'          => $m->id,
                'fecha'       => $m->fecha->toDateString(),
                'consumible_id' => $m->consumible_id,
                'consumible'  => $m->consumible?->descripcion,
                'cantidad'    => -$m->cantidad,
                'retira'      => $m->retira?->nombre_completo,
            ])->all(),
            'ultima_entrega'    => $ultima?->toDateString(),
            'dias_desde_ultima' => $ultima ? (int) $ultima->diffInDays(today()) : null,
            // El texto libre de la ficha (fase 5.3): ya no se pide, pero lo escrito se sigue mostrando [P11].
            'consumible_anterior' => $bien->caracteristicas_tecnicas['consumible'] ?? null,
        ];
    }

    public function presentar(Consumible $consumible): array
    {
        $saldo = $consumible->guarda_tic
            ? (array_key_exists('saldo', $consumible->getAttributes()) ? (int) $consumible->saldo : $consumible->saldoActual())
            : null;

        return [
            'id'            => $consumible->id,
            'codigo_bodega' => $consumible->codigo_bodega,
            'descripcion'   => $consumible->descripcion,
            'tipo'          => $consumible->tipo->value,
            'de_impresion'  => $consumible->tipo->deImpresion(),
            'color'         => $consumible->color?->value,
            'presentacion'  => $consumible->presentacion->value,
            'guarda_tic'    => $consumible->guarda_tic,
            'stock_minimo'  => $consumible->guarda_tic ? $consumible->stock_minimo : null,
            'activo'        => $consumible->activo,
            'saldo'         => $saldo,
            'bajo_minimo'   => $consumible->bajoMinimo($saldo),
            'modelos'       => $consumible->relationLoaded('modelos')
                ? $consumible->modelos->map(fn (ConsumibleModelo $m) => $this->modelo($m))->all()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private function cabecera(array $datos): array
    {
        $guardaTic = (bool) $datos['guarda_tic'];

        return [
            'codigo_bodega' => isset($datos['codigo_bodega']) && trim((string) $datos['codigo_bodega']) !== '' ? trim($datos['codigo_bodega']) : null,
            'descripcion'   => trim($datos['descripcion']),
            'tipo'          => $datos['tipo'],
            'color'         => $datos['color'] ?? null,
            'presentacion'  => $datos['presentacion'],
            'guarda_tic'    => $guardaTic,
            // Lo que despacha Bodega no tiene saldo en TIC, así que tampoco mínimo.
            'stock_minimo'  => $guardaTic ? (int) ($datos['stock_minimo'] ?? 0) : 0,
        ];
    }

    /**
     * Deja los modelos de la lista: borra los que no están y agrega los nuevos.
     * Dos escritos distinto pero iguales sin mayúsculas ni espacios son uno.
     *
     * @param  list<array{marca_id: int, modelo: string}>  $modelos
     */
    private function sincronizarModelos(Consumible $consumible, array $modelos): void
    {
        $deseados = collect($modelos)
            ->map(fn (array $m) => ['marca_id' => (int) $m['marca_id'], 'modelo' => trim($m['modelo']), 'modelo_normalizado' => ConsumibleModelo::normalizar($m['modelo'])])
            ->unique(fn (array $m) => $m['marca_id'] . '|' . $m['modelo_normalizado']);
        $clave = fn ($m) => $m['marca_id'] . '|' . $m['modelo_normalizado'];

        $actuales = $consumible->modelos()->get();
        $quedan = $deseados->map($clave)->all();
        $consumible->modelos()->whereKey($actuales->reject(fn (ConsumibleModelo $m) => in_array($clave($m), $quedan, true))->modelKeys())->delete();

        $ya = $actuales->map($clave)->all();
        foreach ($deseados->reject(fn (array $m) => in_array($clave($m), $ya, true)) as $nuevo) {
            $consumible->modelos()->create($nuevo);
        }
        $consumible->unsetRelation('modelos');
    }

    /** @return array<string, mixed> */
    private function modelo(ConsumibleModelo $m): array
    {
        return ['id' => $m->id, 'marca_id' => $m->marca_id, 'marca' => $m->marca?->nombre, 'modelo' => $m->modelo];
    }
}
