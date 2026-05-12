<?php

namespace App\Enums;

enum TipoFalta: string
{
    case LEVE      = 'leve';
    case GRAVE     = 'grave';
    case MUY_GRAVE = 'muy_grave';
}
