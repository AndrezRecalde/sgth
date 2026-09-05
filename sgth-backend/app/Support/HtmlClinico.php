<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Deja el HTML de una nota clínica en lo que el editor puede producir, y nada
 * más.
 *
 * Los campos de la consulta se escriben con un editor enriquecido y se guardan
 * como HTML, que la pantalla pinta con `dangerouslySetInnerHTML`. Sin filtrar,
 * cualquiera capaz de llamar a la API podía guardar un `<img onerror="...">` en
 * el diagnóstico de un paciente y ese código se ejecutaba en el navegador del
 * siguiente médico que abriera la consulta. El cifrado en reposo no protege de
 * esto: se descifra justo antes de pintarlo.
 *
 * Se limpia al entrar y no al salir, para que lo guardado ya esté limpio: si se
 * filtrara solo al pintar, bastaría un consumidor nuevo que se olvide —un PDF,
 * un informe— para reabrir el agujero.
 *
 * La lista blanca es exactamente lo que produce el editor (TipTap StarterKit,
 * más enlace y resaltado). Nada de atributos salvo el `href` del enlace, y solo
 * con esquemas de navegación: `javascript:` no es un destino, es código.
 */
final class HtmlClinico
{
    /** @var list<string> */
    private const ETIQUETAS = [
        'p', 'br', 'strong', 'em', 's', 'u', 'mark', 'code', 'pre',
        'blockquote', 'ul', 'ol', 'li', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    ];

    private static ?HtmlSanitizer $sanitizador = null;

    /**
     * Devuelve el HTML seguro. Un valor vacío o nulo sale tal cual: distinguir
     * «sin escribir» de «escrito y vacío» es cosa de la validación.
     */
    public static function limpiar(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return trim(self::sanitizador()->sanitize($html));
    }

    private static function sanitizador(): HtmlSanitizer
    {
        if (self::$sanitizador !== null) {
            return self::$sanitizador;
        }

        $config = new HtmlSanitizerConfig();

        foreach (self::ETIQUETAS as $etiqueta) {
            $config = $config->allowElement($etiqueta);
        }

        $config = $config
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            // Lo que no está permitido se descarta con su contenido: el texto
            // de un `<script>` no es prosa clínica que convenga conservar.
            ->dropElement('script')
            ->dropElement('style')
            ->dropElement('iframe')
            ->dropElement('object')
            ->dropElement('embed')
            ->dropElement('form');

        return self::$sanitizador = new HtmlSanitizer($config);
    }
}
