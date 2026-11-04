<?php
namespace App\Enums;

/**
 * Las dos partes del diccionario de competencias del Ministerio, que el
 * formato de asignación de responsabilidades (IN-GEP-02-02-FOR-02) pide por
 * separado: las técnicas, que dependen de las actividades del puesto, y las
 * conductuales, que tienen que ver con la manera de trabajar.
 */
enum TipoCompetencia: string
{
    case TECNICA    = 'tecnica';
    case CONDUCTUAL = 'conductual';

    public function etiqueta(): string
    {
        return match($this) {
            self::TECNICA    => 'Técnica',
            self::CONDUCTUAL => 'Conductual',
        };
    }
}
