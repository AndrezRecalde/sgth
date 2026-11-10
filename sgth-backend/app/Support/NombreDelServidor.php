<?php

namespace App\Support;

use App\Models\Expediente\Servidor;

/**
 * El nombre de un servidor en los listados de Estructura: apellidos y nombres,
 * de sus columnas. No `nombre_completo`, que sin esas columnas cargadas traía
 * el expediente entero (ver PuestoResource).
 */
final class NombreDelServidor
{
    public static function de(Servidor $servidor): string
    {
        return trim(implode(' ', array_filter([
            $servidor->apellido,
            $servidor->segundo_apellido,
            $servidor->nombre,
            $servidor->segundo_nombre,
        ])));
    }
}
