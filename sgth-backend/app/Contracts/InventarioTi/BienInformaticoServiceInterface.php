<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\BienInformatico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** La ficha de un equipo del inventario: registrarla, buscarla y corregirla. */
interface BienInformaticoServiceInterface
{
    /** @param  array<string, mixed>  $filtros */
    public function listar(array $filtros): LengthAwarePaginator;

    /**
     * Los equipos del filtro del listado (o los `ids` elegidos), para imprimir
     * sus etiquetas. Rechaza un filtro vacío o que pase de `$maximo`.
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, BienInformatico>
     */
    public function paraEtiquetas(array $filtros, int $maximo): Collection;

    public function obtener(int $id): BienInformatico;

    /** @param  array<string, mixed>  $datos */
    public function registrar(array $datos): BienInformatico;

    /** @param  array<string, mixed>  $datos */
    public function actualizar(int $id, array $datos): BienInformatico;

    public function retirar(int $id): void;
}
