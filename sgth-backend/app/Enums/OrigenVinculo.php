<?php

namespace App\Enums;

enum OrigenVinculo: string
{
    /** Nació de una Acción de Personal de ingreso. Es el camino normal. */
    case ACCION_PERSONAL = 'accion_personal';

    /**
     * Carga inicial: el servidor ya estaba vinculado antes de que el sistema
     * existiera. El acto administrativo ocurrió en papel y no puede
     * reconstruirse sin inventar un documento que nunca existió.
     */
    case VINCULACION_INICIAL = 'vinculacion_inicial';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ACCION_PERSONAL     => 'Acción de Personal',
            self::VINCULACION_INICIAL => 'Vinculación inicial (carga histórica)',
        };
    }

    /** ¿El vínculo tiene una acción de personal imprimible que lo respalde? */
    public function tieneDocumentoDeRespaldo(): bool
    {
        return $this === self::ACCION_PERSONAL;
    }
}
