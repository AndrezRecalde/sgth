<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InventarioTiServiceInterface
{
    public function registrarBien(array $datos): BienInformatico;

    public function listarBienes(array $filtros): LengthAwarePaginator;

    public function obtenerBien(int $id): BienInformatico;

    public function actualizarBien(int $id, array $datos): BienInformatico;

    public function retirarBien(int $id): void;

    public function asignarBien(array $datos): AsignacionBien;

    /** @return array{content: string, filename: string} */
    public function generarActaEntrega(int $id): array;

    public function obtenerFichaTecnicaCompleta(array $filtros): array;

    public function registrarAuditoriaFisica(array $datos);

    public function procesarBaja(array $datos);
}
