<?php

namespace App\Enums;

/**
 * El nivel de un título del historial académico, de menor a mayor.
 *
 * El técnico superior y el tecnológico superior entraron el 2026-11-10
 * (docs/diseno-manual-de-puestos.md, P2): unas 60 fichas del Manual de Puestos
 * los piden, y sin ellos no había con qué comparar el título de un servidor
 * con lo que exige su puesto. Son tercer nivel técnico-tecnológico (LOES,
 * Art. 118); el tercer nivel de grado es el de las carreras universitarias.
 */
enum NivelEstudio: string
{
    case PRIMARIA             = 'primaria';
    case SECUNDARIA           = 'secundaria';
    case TECNICO_SUPERIOR     = 'tecnico_superior';
    case TECNOLOGICO_SUPERIOR = 'tecnologico_superior';
    case TERCER_NIVEL         = 'tercer_nivel';
    case CUARTO_NIVEL         = 'cuarto_nivel';

    public function etiqueta(): string
    {
        return match($this) {
            self::PRIMARIA             => 'Primaria',
            self::SECUNDARIA           => 'Secundaria',
            self::TECNICO_SUPERIOR     => 'Técnico superior',
            self::TECNOLOGICO_SUPERIOR => 'Tecnológico superior',
            self::TERCER_NIVEL         => 'Tercer nivel de grado',
            self::CUARTO_NIVEL         => 'Cuarto nivel (posgrado)',
        };
    }
}
