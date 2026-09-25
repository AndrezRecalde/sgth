<?php

namespace App\Enums;

/**
 * Gravedad de un accidente de trabajo.
 *
 * La columna nació como texto libre (`string, 50`) y los Form Requests la
 * validaban igual —`['required','string','max:50']`—, así que el API aceptaba
 * cualquier cosa: la tabla pintaba lo que llegara y el mapa de tonos le daba
 * el neutro a lo que no reconocía. Los cuatro valores son los que el
 * formulario ofrece desde siempre.
 *
 * NOTA: el modelo todavía NO castea esta columna a enum. Las filas anteriores
 * a esta validación pueden tener cualquier texto, y un cast haría reventar la
 * lectura del listado entero con un `ValueError` por una sola fila rara.
 * Normalizar lo que haya —y recién entonces castear— necesita mirar los datos
 * reales de producción, que es una decisión de Talento Humano y no del código.
 */
enum GravedadAccidente: string
{
    case LEVE = 'leve';
    case MODERADA = 'moderada';
    case GRAVE = 'grave';
    case MORTAL = 'mortal';

    public function etiqueta(): string
    {
        return match ($this) {
            self::LEVE => 'Leve',
            self::MODERADA => 'Moderada',
            self::GRAVE => 'Grave',
            self::MORTAL => 'Mortal',
        };
    }
}
