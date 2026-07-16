<?php

namespace App\Enums;

enum RegionExamenFisico: string
{
    case PIEL = 'piel';
    case OJOS = 'ojos';
    case OIDO = 'oido';
    case OROFARINGE = 'orofaringe';
    case NARIZ = 'nariz';
    case CUELLO = 'cuello';
    case TORAX_1 = 'torax_1';
    case TORAX_2 = 'torax_2';
    case ABDOMEN = 'abdomen';
    case COLUMNA = 'columna';
    case PELVIS = 'pelvis';
    case EXTREMIDADES = 'extremidades';
    case NEUROLOGICO = 'neurologico';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PIEL => 'Piel',
            self::OJOS => 'Ojos',
            self::OIDO => 'Oído',
            self::OROFARINGE => 'Oro Faringe',
            self::NARIZ => 'Nariz',
            self::CUELLO => 'Cuello',
            self::TORAX_1 => 'Tórax',
            self::TORAX_2 => 'Tórax (Pulmones / Corazón / Parrilla Costal)',
            self::ABDOMEN => 'Abdomen',
            self::COLUMNA => 'Columna',
            self::PELVIS => 'Pelvis',
            self::EXTREMIDADES => 'Extremidades',
            self::NEUROLOGICO => 'Neurológico',
        };
    }
}
