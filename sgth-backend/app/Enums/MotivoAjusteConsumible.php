<?php

namespace App\Enums;

/** Por qué se ajusta el saldo de un consumible (fase 5.4a); siempre con el detalle. */
enum MotivoAjusteConsumible: string
{
    case DANO = 'dano';
    case CADUCIDAD = 'caducidad';
    case DIFERENCIA_CONTEO = 'diferencia_conteo';
    case OTRO = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::DANO              => 'Daño',
            self::CADUCIDAD         => 'Caducidad',
            self::DIFERENCIA_CONTEO => 'Diferencia en el conteo',
            self::OTRO              => 'Otro',
        };
    }
}
