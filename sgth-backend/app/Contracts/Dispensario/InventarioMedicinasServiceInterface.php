<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\InventarioMedicina;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InventarioMedicinasServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator;

    public function obtener(int $id): InventarioMedicina;

    public function contarStockBajo(): int;

    public function buscar(
        string $termino,
        bool $soloDespachables = true
    ): Collection;

    public function ingresarMedicina(
        array $datos,
        int $registradoPor
    ): InventarioMedicina;

    public function actualizar(
        int $id,
        array $datos
    ): InventarioMedicina;

    public function registrarBaja(
        int $id,
        int $cantidad,
        string $motivo,
        int $registradoPor
    ): InventarioMedicina;

    public function ajustarInventario(
        int $id,
        int $nuevoStock,
        string $motivo,
        int $registradoPor
    ): InventarioMedicina;

    public function darDeBaja(int $id): InventarioMedicina;

    public function kardex(int $id): Collection;
}
