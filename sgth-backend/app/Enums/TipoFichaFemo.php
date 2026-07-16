<?php

namespace App\Enums;

enum TipoFichaFemo: string
{
    case INGRESO = 'ingreso';
    case PERIODICA = 'periodica';
    case REINTEGRO = 'reintegro';
    case RETIRO = 'retiro';
    case ESPECIAL = 'especial';

    public function etiqueta(): string
    {
        return match ($this) {
            self::INGRESO => 'Ingreso',
            self::PERIODICA => 'Periódico',
            self::REINTEGRO => 'Reintegro',
            self::RETIRO => 'Retiro',
            self::ESPECIAL => 'Especial',
        };
    }
}
