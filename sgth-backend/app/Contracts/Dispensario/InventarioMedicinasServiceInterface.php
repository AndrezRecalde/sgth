<?php
namespace App\Contracts\Dispensario;

use App\Models\Dispensario\InventarioMedicina;

interface InventarioMedicinasServiceInterface
{
    public function ingresarMedicina(array $datos): InventarioMedicina;
}
