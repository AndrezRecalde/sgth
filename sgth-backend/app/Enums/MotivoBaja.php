<?php

namespace App\Enums;

/** Por qué sale un equipo del servicio (docs/diseno-subsistema-tic.md, fase 1.6). */
enum MotivoBaja: string
{
    case OBSOLESCENCIA = 'obsolescencia';
    case DANO_IRREPARABLE = 'dano_irreparable';
    case ROBO_O_PERDIDA = 'robo_o_perdida';
    case SINIESTRO = 'siniestro';
    case OTRO = 'otro';

    public function etiqueta(): string
    {
        return match ($this) {
            self::OBSOLESCENCIA    => 'Obsolescencia',
            self::DANO_IRREPARABLE => 'Daño irreparable',
            self::ROBO_O_PERDIDA   => 'Robo o pérdida',
            self::SINIESTRO        => 'Siniestro',
            self::OTRO             => 'Otro',
        };
    }
}
