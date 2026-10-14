<?php

namespace App\Contracts\InventarioTi;

/**
 * El historial de los equipos. La ficha está en
 * `BienInformaticoServiceInterface` (1.1), la custodia en
 * `CustodiaServiceInterface` (1.3), la baja en `BajaServiceInterface` (1.6) y
 * la constatación física en `ConstatacionService` (5.2).
 */
interface InventarioTiServiceInterface
{
    public function obtenerFichaTecnicaCompleta(array $filtros): array;
}
