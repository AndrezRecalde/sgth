<?php

namespace App\Contracts\InventarioTi;

use App\Models\InventarioTi\PrestamoEquipo;

/** El trámite de un préstamo: aprobar, rechazar, cancelar, entregar y recibir. */
interface PrestamoEstadoServiceInterface
{
    public function aprobar(PrestamoEquipo $prestamo): PrestamoEquipo;

    public function rechazar(PrestamoEquipo $prestamo, string $motivo): PrestamoEquipo;

    public function cancelar(PrestamoEquipo $prestamo, string $motivo): PrestamoEquipo;

    /** @param  array<string, mixed>  $datos  entregado_en, items[{id, accesorios, condicion_entrega}], observaciones */
    public function entregar(PrestamoEquipo $prestamo, array $datos): PrestamoEquipo;

    /** @param  array<string, mixed>  $datos  devuelto_en, items[{id, condicion_devolucion, novedad}], observaciones */
    public function recibir(PrestamoEquipo $prestamo, array $datos): PrestamoEquipo;
}
