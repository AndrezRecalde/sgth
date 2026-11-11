<?php

namespace App\Enums;

/**
 * El nivel del puesto en la ficha del Manual de Puestos: profesional o no
 * profesional (docs/diseno-manual-de-puestos.md, P1). Se propone desde la
 * instrucción —si alguna alternativa pide tercer o cuarto nivel, profesional—
 * y Talento Humano lo puede cambiar: en el grado 7 el manual tiene de los dos.
 */
enum NivelDelPuesto: string
{
    case PROFESIONAL    = 'profesional';
    case NO_PROFESIONAL = 'no_profesional';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PROFESIONAL    => 'Profesional',
            self::NO_PROFESIONAL => 'No profesional',
        };
    }

    /** @param iterable<NivelInstruccion> $niveles */
    public static function segunInstruccion(iterable $niveles): ?self
    {
        $hay = false;

        foreach ($niveles as $nivel) {
            $hay = true;
            if (in_array($nivel, [NivelInstruccion::TERCER_NIVEL, NivelInstruccion::CUARTO_NIVEL], true)) {
                return self::PROFESIONAL;
            }
        }

        return $hay ? self::NO_PROFESIONAL : null;
    }
}
