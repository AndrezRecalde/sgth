<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\AsignacionBien;
use App\Models\InventarioTi\BienInformatico;

interface InventarioTiServiceInterface
{
    public function registrarBien(array $datos): BienInformatico;

    public function asignarBien(array $datos): AsignacionBien;

    public function obtenerFichaTecnicaCompleta(array $filtros): array;

    public function registrarAuditoriaFisica(array $datos);

    public function procesarBaja(array $datos);
}
