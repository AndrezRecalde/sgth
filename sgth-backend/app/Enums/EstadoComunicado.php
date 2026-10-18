<?php

namespace App\Enums;

/**
 * El estado que se guarda: lo que decide una persona
 * (docs/diseno-noticias-comunicados.md, 3.2).
 *
 * Programado y vencido no están aquí: dependen de la fecha y se deducen al
 * consultar (VigenciaComunicado).
 */
enum EstadoComunicado: string
{
    case BORRADOR  = 'borrador';
    case PUBLICADO = 'publicado';
    case RETIRADO  = 'retirado';
}
