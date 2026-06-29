<?php

namespace App\Contracts\Dispensario;

use App\Models\Dispensario\AdquisicionMedicamento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AdquisicionServiceInterface
{
    public function listar(array $filtros): LengthAwarePaginator;

    public function obtener(int $id): AdquisicionMedicamento;

    public function registrar(
        array $datos,
        array $items,
        int $registradoPor
    ): AdquisicionMedicamento;

    public function subirDocumento(
        int $id,
        string $rutaArchivo
    ): AdquisicionMedicamento;
}
