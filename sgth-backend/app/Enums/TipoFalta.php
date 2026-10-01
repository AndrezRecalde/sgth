<?php

namespace App\Enums;

/** Gravedad de la falta disciplinaria — Art. 42 de la LOSEP. */
enum TipoFalta: string
{
    case LEVE      = 'leve';
    case GRAVE     = 'grave';
    case MUY_GRAVE = 'muy_grave';

    /**
     * Espejada por `TIPO_FALTA_LABELS` en
     * `features/disciplinario/utils/etiquetas.ts`, y fijada por
     * `EnumsDisciplinarioTest`.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::LEVE      => 'Leve',
            self::GRAVE     => 'Grave',
            self::MUY_GRAVE => 'Muy grave',
        };
    }
}
