<?php

namespace App\Enums;

/**
 * Las variantes del logo institucional (docs/diseno-comunicacion-social.md,
 * 4.2). Dos son obligatorias; las demás tienen un respaldo, así que el sistema
 * funciona completo con dos archivos.
 *
 * Solo PNG (y JPG para la hoja membretada y los fondos de la credencial):
 * dompdf dibuja mal muchos SVG, y un SVG puede llevar código ejecutable.
 */
enum VarianteIdentidad: string
{
    /** V1. El símbolo solo. De él salen también el favicon y los íconos. */
    case ISOTIPO = 'isotipo';

    /** V2. El símbolo con «Esmeraldas Prefectura» y el lema, en una línea. */
    case IMAGOTIPO_HORIZONTAL = 'imagotipo_horizontal';

    /** V3. El mismo, en blanco, para fondos oscuros. */
    case IMAGOTIPO_NEGATIVO = 'imagotipo_negativo';

    /** V4. El de la cabecera de los PDF, si no es el horizontal. */
    case LOGO_DOCUMENTOS = 'logo_documentos';

    /** V5. Al lado derecho de la cabecera de los PDF: el escudo, una campaña. */
    case LOGO_SECUNDARIO_DOCUMENTOS = 'logo_secundario_documentos';

    /** V6. La página A4 entera, con el logo, la marca de agua y la dirección. */
    case HOJA_MEMBRETADA = 'hoja_membretada';

    /**
     * V7 y V8. El fondo de cada cara de la credencial, del tamaño de la tarjeta
     * CR80 (D14). Sin él, la cara queda en blanco con su franja, como siempre.
     */
    case FONDO_CREDENCIAL_ANVERSO = 'fondo_credencial_anverso';
    case FONDO_CREDENCIAL_REVERSO = 'fondo_credencial_reverso';

    public function etiqueta(): string
    {
        return match ($this) {
            self::ISOTIPO                    => 'Isotipo',
            self::IMAGOTIPO_HORIZONTAL       => 'Imagotipo horizontal',
            self::IMAGOTIPO_NEGATIVO         => 'Imagotipo horizontal en negativo',
            self::LOGO_DOCUMENTOS            => 'Logo para documentos',
            self::LOGO_SECUNDARIO_DOCUMENTOS => 'Logo secundario de documentos',
            self::HOJA_MEMBRETADA            => 'Hoja membretada A4',
            self::FONDO_CREDENCIAL_ANVERSO   => 'Fondo de la credencial: anverso',
            self::FONDO_CREDENCIAL_REVERSO   => 'Fondo de la credencial: reverso',
        };
    }

    /** Dónde se ve, para la pantalla de Identidad institucional. */
    public function usos(): string
    {
        return match ($this) {
            self::ISOTIPO                    => 'Sidebar, favicon e íconos de la aplicación, marca de agua de la credencial',
            self::IMAGOTIPO_HORIZONTAL       => 'Login, cabecera de los correos, anverso de la credencial, página de verificación',
            self::IMAGOTIPO_NEGATIVO         => 'Login y verificación en modo oscuro, credencial sobre una franja de color',
            self::LOGO_DOCUMENTOS            => 'Cabecera de todos los documentos PDF',
            self::LOGO_SECUNDARIO_DOCUMENTOS => 'Lado derecho de la cabecera de los PDF',
            self::HOJA_MEMBRETADA            => 'Fondo de los documentos tipo memorando (hoy, el informe de actividades)',
            self::FONDO_CREDENCIAL_ANVERSO   => 'Detrás del logo, la foto y los datos de la credencial, impresa y digital',
            self::FONDO_CREDENCIAL_REVERSO   => 'Detrás del QR y los datos del reverso de la credencial, impresa y digital',
        };
    }

    public function obligatoria(): bool
    {
        return $this === self::ISOTIPO || $this === self::IMAGOTIPO_HORIZONTAL;
    }

    /**
     * Lo que se usa si esta variante no tiene versión vigente. Sin respaldo, no
     * se muestra (V5), o el documento arma su propia cabecera (V6).
     */
    public function respaldo(): ?self
    {
        return match ($this) {
            self::IMAGOTIPO_NEGATIVO, self::LOGO_DOCUMENTOS => self::IMAGOTIPO_HORIZONTAL,
            default => null,
        };
    }

    /** @return list<string> extensiones que acepta */
    public function formatos(): array
    {
        return $this === self::HOJA_MEMBRETADA || $this->esFondoDeCredencial() ? ['png', 'jpg', 'jpeg'] : ['png'];
    }

    /** @return array{ancho: int, alto: int} el tamaño mínimo en píxeles */
    public function minimo(): array
    {
        return match ($this) {
            self::ISOTIPO                    => ['ancho' => 512, 'alto' => 512],
            self::IMAGOTIPO_HORIZONTAL,
            self::IMAGOTIPO_NEGATIVO,
            self::LOGO_DOCUMENTOS            => ['ancho' => 1200, 'alto' => 1],
            self::LOGO_SECUNDARIO_DOCUMENTOS => ['ancho' => 300, 'alto' => 1],
            self::HOJA_MEMBRETADA            => ['ancho' => 1240, 'alto' => 1754],
            // La tarjeta CR80 (54 × 85,6 mm) a 300 ppp.
            self::FONDO_CREDENCIAL_ANVERSO,
            self::FONDO_CREDENCIAL_REVERSO   => ['ancho' => 638, 'alto' => 1011],
        };
    }

    /**
     * La proporción ancho / alto que debe tener, con su tolerancia, o null si
     * vale cualquiera. El isotipo es cuadrado; la hoja, un A4 vertical.
     *
     * @return array{valor: float, tolerancia: float}|null
     */
    public function proporcion(): ?array
    {
        return match ($this) {
            self::ISOTIPO         => ['valor' => 1.0, 'tolerancia' => 0.05],
            self::HOJA_MEMBRETADA => ['valor' => 210 / 297, 'tolerancia' => 0.02],
            self::FONDO_CREDENCIAL_ANVERSO,
            self::FONDO_CREDENCIAL_REVERSO => ['valor' => 54 / 85.6, 'tolerancia' => 0.02],
            default               => null,
        };
    }

    public function esFondoDeCredencial(): bool
    {
        return $this === self::FONDO_CREDENCIAL_ANVERSO || $this === self::FONDO_CREDENCIAL_REVERSO;
    }

    /** Cómo se describe el formato en la pantalla y en los errores. */
    public function requisito(): string
    {
        $minimo = $this->minimo();

        return match ($this) {
            self::ISOTIPO         => 'PNG transparente, cuadrado, desde 512 × 512 píxeles',
            self::HOJA_MEMBRETADA => 'JPG o PNG, A4 vertical: 1240 × 1754 (150 ppp) o 2480 × 3508 (300 ppp)',
            self::FONDO_CREDENCIAL_ANVERSO,
            self::FONDO_CREDENCIAL_REVERSO => 'JPG o PNG vertical del tamaño de la tarjeta (54 × 85,6 mm): desde 638 × 1011 píxeles (300 ppp)',
            default               => "PNG, desde {$minimo['ancho']} píxeles de ancho",
        };
    }
}
