<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Deja el cuerpo de una noticia o un comunicado en lo que el editor puede
 * producir, y nada más (docs/diseno-noticias-comunicados.md, regla 5).
 *
 * El cuerpo se pinta en el Portal de todos los servidores con
 * `dangerouslySetInnerHTML`: un `<img onerror>` guardado aquí correría en el
 * navegador de cientos de personas. Por eso se limpia al entrar, como
 * HtmlClinico, y no al pintar: lo guardado ya está limpio para cualquier
 * consumidor nuevo.
 *
 * Las diferencias con la nota clínica: sin `h1` (el título de la publicación ya
 * lo es) y sin imágenes (Talento Humano no las publica; los documentos van como
 * PDF adjuntos). Los
 * enlaces salen con `rel="noopener noreferrer"`.
 *
 * Lo que no está permitido se descarta con su contenido, salvo los
 * contenedores que trae un texto pegado de Word o de una página (`div`,
 * `span`, un `h1`…): de esos se quita la etiqueta y se conserva el texto, para
 * que pegar un comunicado no lo deje en blanco.
 */
final class HtmlEnriquecido
{
    /** @var list<string> */
    private const ETIQUETAS = [
        'p', 'br', 'strong', 'em', 's', 'u', 'mark',
        'h2', 'h3', 'h4', 'blockquote', 'ul', 'ol', 'li', 'hr',
    ];

    /** @var list<string> */
    private const CONTENEDORES = [
        'div', 'span', 'section', 'article', 'header', 'footer', 'main',
        'h1', 'h5', 'h6', 'font', 'small', 'sub', 'sup', 'b', 'i', 'label',
    ];

    private static ?HtmlSanitizer $sanitizador = null;

    /** Devuelve el HTML seguro. Un valor vacío o nulo sale tal cual. */
    public static function limpiar(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return trim(self::sanitizador()->sanitize($html));
    }

    /** ¿Queda texto que leer? Un cuerpo de solo `<p></p>` no se publica. */
    public static function tieneTexto(?string $html): bool
    {
        $texto = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // El espacio duro (`&nbsp;`) que deja el editor en un párrafo vacío no es texto.
        return preg_match('/[^\s\x{00A0}]/u', $texto) === 1;
    }

    /**
     * El comienzo del texto, sin formato, para la tarjeta del Portal cuando no
     * hay resumen: hasta `$largo` caracteres, cortado en una palabra y con
     * puntos suspensivos si sigue. Los párrafos y los elementos de lista se
     * separan con un espacio para que dos frases no queden pegadas.
     */
    public static function extracto(?string $html, int $largo = 200): string
    {
        $conEspacios = preg_replace('/<\/(p|li|h[1-6]|blockquote)>|<br\s*\/?>/i', ' ', (string) $html) ?? '';
        $texto = html_entity_decode(strip_tags($conEspacios), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $texto) ?? '');

        if (mb_strlen($texto) <= $largo) {
            return $texto;
        }

        $corte = mb_substr($texto, 0, $largo);
        $espacio = mb_strrpos($corte, ' ');
        if ($espacio !== false && $espacio > $largo * 0.6) {
            $corte = mb_substr($corte, 0, $espacio);
        }

        return rtrim($corte, ' ,;:.').'…';
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

        foreach (self::CONTENEDORES as $etiqueta) {
            $config = $config->blockElement($etiqueta);
        }

        $config = $config
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->dropElement('script')
            ->dropElement('style')
            ->dropElement('iframe')
            ->dropElement('object')
            ->dropElement('embed')
            ->dropElement('form')
            ->dropElement('img');

        return self::$sanitizador = new HtmlSanitizer($config);
    }
}
