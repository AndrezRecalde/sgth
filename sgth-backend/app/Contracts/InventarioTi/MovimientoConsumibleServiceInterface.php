<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\Consumible;
use App\Models\InventarioTi\MovimientoConsumible;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Los movimientos de los consumibles y su kárdex (fase 5.4a). El saldo nunca
 * queda negativo, en ningún punto del kárdex.
 */
interface MovimientoConsumibleServiceInterface
{
    /**
     * Los movimientos en el orden del kárdex, con el saldo que dejó cada uno.
     *
     * @return array{saldo_inicial: int|null, filas: list<array<string, mixed>>, saldo_final: int|null}
     */
    public function kardex(Consumible $consumible, ?string $desde = null, ?string $hasta = null): array;

    /** El saldo contado el día de arranque [P7]. Solo lo que guarda TIC. */
    public function registrarApertura(Consumible $consumible, array $datos, User $user): MovimientoConsumible;

    /**
     * Lo que TIC recibe de bodega con un documento, uno o varios consumibles.
     *
     * @return Collection<int, MovimientoConsumible>
     */
    public function registrarIngreso(array $datos, User $user): Collection;

    /**
     * Lo que sale a un equipo (o a un servidor, si es un repuesto), uno o varios
     * consumibles a la vez.
     *
     * @return Collection<int, MovimientoConsumible>
     */
    public function registrarEntrega(array $datos, User $user): Collection;

    public function registrarAjuste(Consumible $consumible, array $datos, User $user): MovimientoConsumible;

    /** Deja de contar, pero sigue a la vista en el kárdex. */
    public function anular(MovimientoConsumible $movimiento, string $motivo, User $user): MovimientoConsumible;

    /** El saldo al cierre de un día. */
    public function saldoAl(Consumible $consumible, string $fecha): int;

    /** @return array<string, mixed> */
    public function presentar(MovimientoConsumible $movimiento): array;
}
