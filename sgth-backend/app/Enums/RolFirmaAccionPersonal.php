<?php

namespace App\Enums;

/**
 * Roles que firman una Acción de Personal. Son los dos bloques de firma del
 * formulario del GAD: la autoridad nominadora (Prefecto/a) y el responsable
 * de Talento Humano.
 */
enum RolFirmaAccionPersonal: string
{
    case AUTORIDAD_NOMINADORA = 'autoridad_nominadora';
    case RESPONSABLE_TALENTO_HUMANO = 'responsable_talento_humano';

    public function etiqueta(): string
    {
        return match ($this) {
            self::AUTORIDAD_NOMINADORA        => 'Autoridad Nominadora',
            self::RESPONSABLE_TALENTO_HUMANO  => 'Responsable de Talento Humano',
        };
    }

    /** Rótulo tal como aparece impreso sobre la línea de firma. */
    public function rotuloDocumento(): string
    {
        return match ($this) {
            self::AUTORIDAD_NOMINADORA       => 'AUTORIDAD NOMINADORA',
            self::RESPONSABLE_TALENTO_HUMANO => 'RECURSOS HUMANOS',
        };
    }

    /** Texto de respaldo cuando no hay firmante designado vigente. */
    public function cargoPorDefecto(): string
    {
        return match ($this) {
            self::AUTORIDAD_NOMINADORA       => 'PREFECTO/A PROVINCIAL',
            self::RESPONSABLE_TALENTO_HUMANO => 'DIRECTOR/A DE TALENTO HUMANO',
        };
    }
}
