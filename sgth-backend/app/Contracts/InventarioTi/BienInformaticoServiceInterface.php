<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\BienInformatico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** La ficha de un equipo del inventario: registrarla, buscarla y corregirla. */
interface BienInformaticoServiceInterface
{
    /** @param  array<string, mixed>  $filtros */
    public function listar(array $filtros): LengthAwarePaginator;

    public function obtener(int $id): BienInformatico;

    /** @param  array<string, mixed>  $datos */
    public function registrar(array $datos): BienInformatico;

    /** @param  array<string, mixed>  $datos */
    public function actualizar(int $id, array $datos): BienInformatico;

    public function retirar(int $id): void;
}
