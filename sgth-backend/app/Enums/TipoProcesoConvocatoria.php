<?php

namespace App\Enums;

enum TipoProcesoConvocatoria: string
{
    case FORMAL  = 'formal';
    case EXPRESS = 'express';

    public function etiqueta(): string
    {
        return match ($this) {
            self::FORMAL  => 'Concurso formal (méritos y oposición)',
            self::EXPRESS => 'Reclutamiento Express',
        };
    }
}
