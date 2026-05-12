<?php

namespace App\Exceptions;

use RuntimeException;

final class ReglaNegocioException extends RuntimeException
{
    public function __construct(string $mensaje)
    {
        parent::__construct($mensaje);
    }
}
