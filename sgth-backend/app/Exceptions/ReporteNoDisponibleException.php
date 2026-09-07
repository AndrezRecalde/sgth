<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un reporte que todavía no se puede armar, y por qué.
 *
 * No es un fallo del sistema ni un error de quien lo pide: es que el dato que
 * ese reporte necesita no está en el modelo. Se lanza en vez de devolver una
 * lista vacía porque un reporte vacío se lee como «no hubo movimientos», y
 * estos se presentan ante el IESS y el SRI.
 *
 * El mensaje dice qué falta, para que quien lo reciba sepa si le corresponde
 * pedirlo, construirlo o esperarlo.
 */
final class ReporteNoDisponibleException extends RuntimeException
{
    public function __construct(string $reporte, string $queFalta)
    {
        parent::__construct(
            "El reporte «{$reporte}» todavía no se puede generar: {$queFalta}"
        );
    }
}
