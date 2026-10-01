<?php

namespace App\Enums;

/**
 * Sanciones disciplinarias del Art. 43 de la LOSEP, en orden de gravedad. La
 * destitución es la única que termina el vínculo, y por eso es la única que
 * genera una Cesación de Funciones.
 */
enum TipoSancion: string
{
    case AMONESTACION_VERBAL  = 'amonestacion_verbal';
    case AMONESTACION_ESCRITA = 'amonestacion_escrita';
    case MULTA                = 'multa';
    case SUSPENSION           = 'suspension';
    case DESTITUCION          = 'destitucion';

    /**
     * Espejada por `TIPO_SANCION_LABELS` en
     * `features/disciplinario/utils/etiquetas.ts`, y fijada por
     * `EnumsDisciplinarioTest`.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::AMONESTACION_VERBAL  => 'Amonestación verbal',
            self::AMONESTACION_ESCRITA => 'Amonestación escrita',
            self::MULTA                => 'Multa',
            self::SUSPENSION           => 'Suspensión',
            self::DESTITUCION          => 'Destitución',
        };
    }
}
