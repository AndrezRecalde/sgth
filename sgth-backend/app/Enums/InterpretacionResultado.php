<?php

namespace App\Enums;

/** La lectura del médico al revisar un resultado (D3). */
enum InterpretacionResultado: string
{
    case NORMAL   = 'normal';
    case ALTERADO = 'alterado';
    /** Queda fijo en «Mi jornada» de quien pidió el examen hasta que lo ve. */
    case CRITICO  = 'critico';

    public function etiqueta(): string
    {
        return match ($this) {
            self::NORMAL   => 'Normal',
            self::ALTERADO => 'Alterado',
            self::CRITICO  => 'Crítico',
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
