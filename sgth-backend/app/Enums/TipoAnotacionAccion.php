<?php

namespace App\Enums;

/**
 * Lo que se le anota a una acción de personal después de emitida (diseño de
 * Acciones de Personal, 8.1; fase 1.4). Un acto registrado no se reescribe:
 * lo que pasa después va aquí, aparte, con quién y cuándo.
 */
enum TipoAnotacionAccion: string
{
    /**
     * El trabajador impugnó el visto bueno que originó la cesación. Hasta la
     * fase 1.4 se añadía al final de la explicación de la acción, aunque ya
     * estuviera registrada.
     */
    case IMPUGNACION_VISTO_BUENO = 'impugnacion_visto_bueno';

    /** Una nota de Talento Humano: lo que antes se habría corregido a mano. */
    case NOTA = 'nota';

    public function etiqueta(): string
    {
        return match ($this) {
            self::IMPUGNACION_VISTO_BUENO => 'Impugnación del visto bueno',
            self::NOTA                    => 'Nota de Talento Humano',
        };
    }
}
