<?php

namespace App\Contracts\InventarioTi;


/**
 * El historial, la auditoría física y la baja de los equipos. La ficha está
 * en `BienInformaticoServiceInterface` (1.1) y la custodia en
 * `CustodiaServiceInterface` (1.3).
 */
interface InventarioTiServiceInterface
{
    public function obtenerFichaTecnicaCompleta(array $filtros): array;

    public function registrarAuditoriaFisica(array $datos);

    public function procesarBaja(array $datos);
}
