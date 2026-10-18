<?php

namespace App\Enums;

/**
 * A quién va una publicación (docs/diseno-noticias-comunicados.md, 3.3).
 *
 * Segmentada es por unidades, por régimen o por las dos a la vez: con las dos,
 * el servidor tiene que cumplir ambas.
 */
enum AudienciaComunicado: string
{
    case TODOS      = 'todos';
    case SEGMENTADA = 'segmentada';
}
