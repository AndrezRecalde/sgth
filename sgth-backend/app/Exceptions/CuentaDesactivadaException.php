<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La cuenta existe y la contraseña es correcta, pero el acceso está revocado.
 *
 * Se distingue de una credencial inválida (401) porque el usuario no gana nada
 * reintentando: debe pedirle a TI que lo reactive. Se traduce a un 403 en
 * bootstrap/app.php.
 */
final class CuentaDesactivadaException extends RuntimeException
{
    public function __construct(
        string $mensaje = 'Su cuenta está desactivada. Comuníquese con la Dirección de TI.',
    ) {
        parent::__construct($mensaje);
    }
}
