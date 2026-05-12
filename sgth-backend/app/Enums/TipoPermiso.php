<?php

namespace App\Enums;

enum TipoPermiso: string
{
    case PERSONAL   = 'personal';
    case OFICIAL    = 'oficial';
    case ENFERMEDAD = 'enfermedad';
    case CALAMIDAD  = 'calamidad';
}
