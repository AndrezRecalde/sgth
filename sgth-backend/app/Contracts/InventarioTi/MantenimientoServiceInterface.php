<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\MantenimientoBien;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** Los mantenimientos de los equipos: registrarlos, consultarlos y anularlos. */
interface MantenimientoServiceInterface
{
    /** @param  array<string, mixed>  $filtros */
    public function listar(array $filtros): LengthAwarePaginator;

    public function obtener(int $id): MantenimientoBien;

    /** @param  array<string, mixed>  $datos */
    public function registrar(array $datos): MantenimientoBien;

    public function anular(MantenimientoBien $mantenimiento, string $motivo): MantenimientoBien;

    /** Los usuarios que pueden figurar como técnico de un mantenimiento. */
    public function tecnicos(): Collection;
}
