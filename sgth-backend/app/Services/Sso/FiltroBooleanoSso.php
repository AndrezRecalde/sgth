<?php

namespace App\Services\Sso;

/**
 * El filtro booleano que llega por query string.
 *
 * Los tres catálogos del módulo —factores de riesgo, normativa legal y
 * actividades del programa de drogas— se piden con `?solo_activos=true`,
 * porque axios serializa así un `true` de JavaScript. La regla `boolean` de
 * Laravel acepta `true`, `false`, `1`, `0`, `'1'` y `'0'`, pero NO las cadenas
 * `'true'` y `'false'`.
 *
 * Resultado: los tres modales de catálogo recibían un 422 y se abrían enteros
 * en estado de error, así que no había forma de dar de alta un factor de
 * riesgo ni de corregir una normativa desde la pantalla. Comprobado en el
 * navegador sobre `main`.
 *
 * `in` y no `boolean` porque lo que viaja es texto: acepta las seis formas que
 * pueden llegar y sigue rechazando `?solo_activos=abc`, que es lo que la
 * validación tiene que atrapar. El `$request->boolean()` de los controladores
 * ya interpretaba bien las seis; el único que no las admitía era el validador
 * de delante.
 *
 * Los listados paginados del módulo resuelven lo mismo normalizando en el
 * `FormRequest` (#243). Estos tres no son listados paginados: devuelven el
 * catálogo entero para llenar un desplegable, validan en el propio controlador
 * y se quedaron fuera de aquel barrido.
 */
final class FiltroBooleanoSso
{
    /** Reglas para un filtro booleano opcional de query string. */
    public static function reglas(): array
    {
        return ['nullable', 'in:1,0,true,false'];
    }
}
