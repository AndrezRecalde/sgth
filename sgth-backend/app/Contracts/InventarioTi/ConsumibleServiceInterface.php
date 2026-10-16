<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Consumible;
use App\Models\InventarioTi\ConsumibleModelo;
use App\Models\User;
use Illuminate\Support\Collection;

/** El catálogo de consumibles y su compatibilidad por modelo (fase 5.4a). */
interface ConsumibleServiceInterface
{
    /**
     * Cada consumible con su saldo (si lo guarda TIC) y sus modelos.
     *
     * @param  array{search?: string|null, tipo?: string|null, guarda_tic?: bool|null, incluir_inactivos?: bool, bajo_minimo?: bool, bien_informatico_id?: int|null, marca_id?: int|null, modelo?: string|null}  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function listar(array $filtros): Collection;

    /**
     * El consumible con sus modelos y, junto a cada uno, los equipos del
     * inventario que coinciden.
     *
     * @return array<string, mixed>
     */
    public function detalle(Consumible $consumible): array;

    /** @param  array<string, mixed>  $datos  con `modelos` opcional: [{marca_id, modelo}] */
    public function crear(array $datos, User $user): Consumible;

    /** @param  array<string, mixed>  $datos  con `modelos` opcional, que reemplaza los que tenía */
    public function actualizar(Consumible $consumible, array $datos, User $user): Consumible;

    public function agregarModelo(Consumible $consumible, int $marcaId, string $modelo): ConsumibleModelo;

    public function quitarModelo(Consumible $consumible, ConsumibleModelo $modelo): void;

    /**
     * La sección «Consumibles» de la ficha de un equipo: los compatibles con
     * su saldo, las últimas entregas y los días desde la última.
     *
     * @return array<string, mixed>
     */
    public function delEquipo(BienInformatico $bien): array;

    /**
     * Una fila del catálogo.
     *
     * @return array<string, mixed>
     */
    public function presentar(Consumible $consumible): array;
}
