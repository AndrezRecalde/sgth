<?php

namespace App\Enums;

/**
 * Los estados por los que pasa una receta.
 *
 * La columna es un `varchar(50)` sin CHECK y el modelo no la castea, así que
 * hasta ahora el catálogo solo existía repartido entre las asignaciones de
 * RecetaService. Esto lo reúne en un sitio para poder validarlo en la entrada;
 * castear el modelo es un cambio aparte, porque toca el servicio y sus tests.
 */
enum EstadoReceta: string
{
    case PENDIENTE           = 'pendiente';
    case DESPACHADA_PARCIAL  = 'despachada_parcial';
    case DESPACHADA_COMPLETA = 'despachada_completa';
    case ANULADA             = 'anulada';

    /**
     * Todo lo recetado se adquiere fuera: la farmacia no maneja ninguno de los
     * medicamentos. Es un estado terminal desde el momento de emitirse, y
     * existe para que una receta así no se quede eternamente en la cola del
     * mostrador esperando una entrega que nadie puede hacer.
     */
    case EXTERNA             = 'externa';

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
