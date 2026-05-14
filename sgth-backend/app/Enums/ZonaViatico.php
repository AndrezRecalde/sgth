<?php

namespace App\Enums;

enum ZonaViatico: string
{
    case DENTRO_PROVINCIA = 'dentro_provincia';
    case FUERA_PROVINCIA  = 'fuera_provincia';
    case EXTERIOR         = 'exterior';
}
