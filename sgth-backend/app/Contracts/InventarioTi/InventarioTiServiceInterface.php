<?php
namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\AsignacionBien;

interface InventarioTiServiceInterface
{
    public function registrarBien(array $datos): BienInformatico;
    public function asignarBien(array $datos): AsignacionBien;
}
