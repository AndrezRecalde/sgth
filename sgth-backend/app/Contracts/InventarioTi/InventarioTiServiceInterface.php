<?php

namespace App\Contracts\InventarioTi;


/**
 * El historial y la auditoría física de los equipos. La ficha está en
 * `BienInformaticoServiceInterface` (1.1), la custodia en
 * `CustodiaServiceInterface` (1.3) y la baja en `BajaServiceInterface` (1.6).
 */
interface InventarioTiServiceInterface
{
    public function obtenerFichaTecnicaCompleta(array $filtros): array;

    public function registrarAuditoriaFisica(array $datos);
}
