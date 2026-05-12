<?php

namespace App\Enums;

enum MotivoSubrogacion: string
{
    case VACACIONES        = 'vacaciones';
    case COMISION          = 'comision_servicios';
    case ENFERMEDAD        = 'enfermedad';
    case LICENCIA          = 'licencia';
    case ENCARGO_VACANTE   = 'encargo_vacante';
    case OTRO              = 'otro';

    public function etiqueta(): string
    {
        return match($this) {
            self::VACACIONES      => 'Vacaciones',
            self::COMISION        => 'Comisión de Servicios',
            self::ENFERMEDAD      => 'Enfermedad',
            self::LICENCIA        => 'Licencia',
            self::ENCARGO_VACANTE => 'Encargo por Vacante',
            self::OTRO            => 'Otro',
        };
    }
}
