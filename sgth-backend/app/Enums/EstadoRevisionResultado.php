<?php

namespace App\Enums;

/** Si un médico ya leyó el resultado. */
enum EstadoRevisionResultado: string
{
    /** Recién subido: aparece en «Por revisar». */
    case POR_REVISAR = 'por_revisar';
    case REVISADO    = 'revisado';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
