<?php

namespace App\Enums;

enum TipoExamenFemo: string
{
    case LABORATORIO = 'laboratorio';
    case IMAGEN = 'imagen';
    case OTRO = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LABORATORIO => 'Laboratorio',
            self::IMAGEN => 'Imagen',
            self::OTRO => 'Otro',
        };
    }

    /**
     * El tipo de la sección J que corresponde al tipo de un resultado médico,
     * que distingue más. Es el único sitio donde se cruzan los dos: la sección
     * J alimentada desde los resultados (fase 5 del diseño de exámenes) lo usa
     * para no adivinarlo en cada pantalla.
     */
    public static function deResultado(string $tipoResultado): self
    {
        return match ($tipoResultado) {
            'laboratorio'                      => self::LABORATORIO,
            'imagen', 'ecografia', 'rayos_x'   => self::IMAGEN,
            default                            => self::OTRO,
        };
    }
}
