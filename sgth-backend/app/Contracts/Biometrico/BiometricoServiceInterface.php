<?php

namespace App\Contracts\Biometrico;

use Carbon\Carbon;

interface BiometricoServiceInterface
{
    /**
     * Importa las marcaciones desde el sistema biométrico externo (SQL Server).
     *
     * @param Carbon $desde Fecha de inicio del rango a importar
     * @param Carbon $hasta Fecha de fin del rango a importar
     * @return int Número de registros importados exitosamente
     */
    public function importarMarcaciones(Carbon $desde, Carbon $hasta): int;
}
