<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\AsignacionBien;

/**
 * La custodia, el acta, el historial y la baja de los equipos. La ficha del
 * equipo está en `BienInformaticoServiceInterface` (fase 1.1).
 */
interface InventarioTiServiceInterface
{
    public function asignarBien(array $datos): AsignacionBien;

    /** La devolución del bien asignado (fase 7.4a). */
    public function devolverBien(AsignacionBien $asignacion, string $fecha, ?string $observaciones): AsignacionBien;

    /** @return array{content: string, filename: string} */
    public function generarActaEntrega(int $id): array;

    public function obtenerFichaTecnicaCompleta(array $filtros): array;

    public function registrarAuditoriaFisica(array $datos);

    public function procesarBaja(array $datos);
}
