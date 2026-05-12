<?php

namespace App\Enums;

enum TipoConcepto: string
{
    case INGRESO   = 'ingreso';
    case DESCUENTO = 'descuento';
    case APORTE    = 'aporte';
}
