<?php

namespace App\Contracts\Biometrico;

use Carbon\Carbon;

interface BiometricoServiceInterface
{
    /**
     * Importa las marcaciones desde el reloj biométrico al SGTH.
     * NUNCA escribe en la BD externa; consume un Stored Procedure de solo lectura.
     *
     * @return int Número de registros importados
     */
    public function importarMarcaciones(Carbon $desde, Carbon $hasta): int;
}
