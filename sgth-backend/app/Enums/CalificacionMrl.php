<?php

namespace App\Enums;

enum CalificacionMrl: string
{
    case EXCELENTE     = 'excelente';     // 91 - 100
    case MUY_BUENO     = 'muy_bueno';     // 81 - 90
    case SATISFACTORIO = 'satisfactorio'; // 71 - 80
    case REGULAR       = 'regular';       // 61 - 70
    case INSUFICIENTE  = 'insuficiente';  // <= 60
}
