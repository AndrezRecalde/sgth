<?php

namespace App\Enums;

/**
 * Las dos salidas de impresión de la credencial (docs/diseno-comunicacion-social.md,
 * 6.2; D7): una tarjeta CR80 por hoja, anverso y reverso, para la impresora de
 * PVC; u hojas A4 de nueve por cara para recortar y plastificar.
 */
enum FormatoImpresionCredencial: string
{
    case CR80 = 'cr80';
    case A4   = 'a4';

    public function etiqueta(): string
    {
        return match ($this) {
            self::CR80 => 'Tarjeta CR80 (impresora de PVC)',
            self::A4   => 'Hoja A4 de 9 (para recortar)',
        };
    }
}
