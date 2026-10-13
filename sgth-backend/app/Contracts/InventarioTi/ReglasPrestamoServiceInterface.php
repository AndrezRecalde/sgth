<?php

namespace App\Contracts\InventarioTi;

use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\ReglasPrestamo;
use Carbon\Carbon;

/** Las reglas del préstamo de equipos (D16) y las comprobaciones que salen de ellas. */
interface ReglasPrestamoServiceInterface
{
    public function obtener(): ReglasPrestamo;

    /** @param  array<string, mixed>  $datos */
    public function actualizar(array $datos): ReglasPrestamo;

    /** El horario pedido: anticipación, atención de TIC y plazo máximo. El directo empieza ahora. */
    public function validarHorario(Carbon $inicio, Carbon $fin, bool $directo = false): void;

    /** Que el servidor esté activo y, si la regla lo pide, sin préstamos vencidos. */
    public function validarServidor(Servidor $servidor): void;

    /** Minutos de margen para retirar una reserva antes de que pase a «no retirado». */
    public function margenRetiro(): int;
}
