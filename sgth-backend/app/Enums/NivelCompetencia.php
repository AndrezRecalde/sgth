<?php
namespace App\Enums;

/**
 * El nivel en que el puesto exige una competencia. Cada competencia del
 * diccionario describe un comportamiento observable por nivel.
 */
enum NivelCompetencia: string
{
    case ALTO  = 'alto';
    case MEDIO = 'medio';
    case BAJO  = 'bajo';

    public function etiqueta(): string
    {
        return match($this) {
            self::ALTO  => 'Alto',
            self::MEDIO => 'Medio',
            self::BAJO  => 'Bajo',
        };
    }

    /**
     * Su lugar dentro de la competencia. En el diccionario, los
     * comportamientos de cada tipo van numerados de corrido, de tres en tres
     * y en este orden: la competencia 14 es el 40 en alto, el 41 en medio y el
     * 42 en bajo. Es el número que el jefe escribe en el FOR-02.
     */
    public function posicion(): int
    {
        return match($this) {
            self::ALTO  => 1,
            self::MEDIO => 2,
            self::BAJO  => 3,
        };
    }
}
