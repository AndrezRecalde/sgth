<?php

namespace App\Enums;

/**
 * El estado que se enseña: el guardado más lo que dicen las fechas
 * (docs/diseno-noticias-comunicados.md, 3.2).
 *
 * Un publicado cuya fecha de inicio no llega está programado; uno cuya fecha
 * de fin pasó está vencido. Se calcula siempre al consultar, así que no hace
 * falta una tarea que cambie estados a medianoche.
 */
enum VigenciaComunicado: string
{
    case BORRADOR   = 'borrador';
    case PROGRAMADO = 'programado';
    case PUBLICADO  = 'publicado';
    case VENCIDO    = 'vencido';
    case RETIRADO   = 'retirado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::BORRADOR   => 'Borrador',
            self::PROGRAMADO => 'Programado',
            self::PUBLICADO  => 'Publicado',
            self::VENCIDO    => 'Vencido',
            self::RETIRADO   => 'Retirado',
        };
    }
}
