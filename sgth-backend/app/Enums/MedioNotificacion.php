<?php

namespace App\Enums;

/**
 * Cómo se notifica la acción de personal al servidor (Reglamento a la LOSEP,
 * Art. 22; diseño, 6.4; TH 26; fase 6.3a).
 *
 * Por el portal, el documento aparece en «Mis documentos laborales», le llega
 * al correo y él acusa recibo. En persona, Talento Humano registra si la
 * recibió o si se negó, con la razón y un testigo.
 */
enum MedioNotificacion: string
{
    case PORTAL   = 'portal';
    case PERSONAL = 'personal';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PORTAL   => 'Por el portal y el correo',
            self::PERSONAL => 'En persona',
        };
    }
}
