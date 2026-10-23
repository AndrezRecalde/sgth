<?php

namespace App\Enums;

/**
 * Las áreas del catálogo de exámenes.
 *
 * Son las de la hoja de pedido del laboratorio privado con la que trabajaban
 * los médicos del Dispensario (decisión D7, 23-10-2026), en su mismo orden: el
 * modal de solicitud y el impreso agrupan los exámenes así para que el médico
 * y el laboratorio los lean igual que hoy.
 */
enum AreaExamen: string
{
    case BIOQUIMICA           = 'bioquimica';
    case INFECCIOSAS          = 'infecciosas';
    case MICROBIOLOGIA        = 'microbiologia';
    case INMUNOLOGIA          = 'inmunologia';
    case DROGAS               = 'drogas';
    case HEMOSTASIA           = 'hemostasia';
    case ENZIMAS              = 'enzimas';
    case HEMATOLOGIA          = 'hematologia';
    case ENDOCRINOS           = 'endocrinos';
    case MARCADORES_TUMORALES = 'marcadores_tumorales';
    case ELECTROLITOS         = 'electrolitos';
    case ORINA                = 'orina';
    case HECES                = 'heces';
    case SEROLOGIA            = 'serologia';
    case MARCADORES_CARDIACOS = 'marcadores_cardiacos';
    case RADIOGRAFIAS         = 'radiografias';
    case ECOGRAFIAS           = 'ecografias';
    case OCUPACIONALES        = 'ocupacionales';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BIOQUIMICA           => 'Bioquímica',
            self::INFECCIOSAS          => 'Infecciosas',
            self::MICROBIOLOGIA        => 'Microbiología',
            self::INMUNOLOGIA          => 'Inmunología',
            self::DROGAS               => 'Drogas terapéuticas y de abuso',
            self::HEMOSTASIA           => 'Hemostasia y coagulación',
            self::ENZIMAS              => 'Enzimas',
            self::HEMATOLOGIA          => 'Hematología',
            self::ENDOCRINOS           => 'Endócrinos',
            self::MARCADORES_TUMORALES => 'Marcadores tumorales',
            self::ELECTROLITOS         => 'Electrolitos y gases',
            self::ORINA                => 'Orina',
            self::HECES                => 'Heces',
            self::SEROLOGIA            => 'Serología',
            self::MARCADORES_CARDIACOS => 'Marcadores cardíacos',
            self::RADIOGRAFIAS         => 'Radiografías',
            self::ECOGRAFIAS           => 'Ecografías',
            self::OCUPACIONALES        => 'Ocupacionales',
        };
    }

    /** Su posición en la hoja, que es el orden de presentación. */
    public function orden(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Las áreas para la pantalla, en orden: lo que necesita para agrupar sin
     * repetir aquí las etiquetas.
     *
     * @return list<array{value: string, etiqueta: string, orden: int}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $area) => [
            'value'    => $area->value,
            'etiqueta' => $area->etiqueta(),
            'orden'    => $area->orden(),
        ], self::cases());
    }
}
