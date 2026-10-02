<?php

namespace App\Http\Requests\Sso;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de los listados del módulo SSO.
 *
 * Los siete `index` del módulo pasaban `$request->all()` al servicio, que lo
 * usaba crudo en un `where`. Eso dejaba dos agujeros:
 *
 * - `estado=abc` llegaba sin tocar a una comparación sobre una columna
 *   `boolean` y PostgreSQL respondía con un `22P02` —«invalid input syntax for
 *   type boolean»—, es decir un 500 que no dice nada. Lo mismo valía para
 *   cualquier filtro numérico: `puesto_id=x` reventaba igual.
 * - `por_pagina` no tenía techo, así que `por_pagina=1000000` era una consulta
 *   legítima para el sistema: una sola petición podía pedir la tabla entera.
 *
 * Las subclases declaran sus propios filtros y los juntan con
 * `reglasDePaginacion()`. Lo que llega al servicio sale de `filtros()`, ya
 * tipado: el servicio recibe un `bool` de verdad y no la cadena `'true'`.
 */
abstract class ListadoSsoRequest extends FormRequest
{
    /**
     * Tope de registros por página.
     *
     * 100 y no 15: el valor por defecto sigue siendo 15, pero las pantallas
     * que llenan un desplegable con el catálogo completo necesitan pedir más
     * de una página de una vez, y 100 cubre los catálogos del módulo sin
     * convertir un listado en una descarga.
     */
    public const MAX_POR_PAGINA = 100;

    /**
     * Las reglas del listado: las de la subclase más la paginación.
     */
    public function rules(): array
    {
        return array_merge($this->reglasDeFiltros(), [
            'por_pagina' => ['nullable', 'integer', 'between:1,' . self::MAX_POR_PAGINA],
            // `page` no va en `filtros()`: lo lee el paginador de Laravel
            // directamente de la petición. Se valida igual para que el
            // contrato del listado esté completo y no quede un parámetro sin
            // reglas al lado de los que sí las tienen.
            'page'       => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /**
     * Normaliza los filtros booleanos antes de validarlos.
     *
     * La regla `boolean` de Laravel acepta `true`, `false`, `1`, `0`, `'1'` y
     * `'0'` — y NO las cadenas `'true'` y `'false'`. Pero eso es justo lo que
     * llega: axios serializa `{ estado: true }` como `?estado=true`, así que
     * los tres formularios de EPP que piden el catálogo activo mandan la
     * cadena. Con la regla a secas, validar habría convertido una consulta
     * que funciona en un 422.
     *
     * Se normaliza solo lo reconocible. Un `estado=abc` se deja tal cual para
     * que `boolean` lo rechace: ese 422 es el motivo de este cambio, y
     * tragárselo aquí lo devolvería al `where` crudo de antes.
     *
     * Los campos salen de las propias reglas, no de una segunda lista: un
     * filtro booleano nuevo queda cubierto por declararlo `boolean`.
     */
    protected function prepareForValidation(): void
    {
        $normalizados = [];

        foreach ($this->rules() as $campo => $reglas) {
            if (! in_array('boolean', (array) $reglas, true) || ! $this->filled($campo)) {
                continue;
            }

            $valor = $this->input($campo);

            $normalizados[$campo] = match (true) {
                $valor === true, $valor === 1, $valor === '1', $valor === 'true'    => 1,
                $valor === false, $valor === 0, $valor === '0', $valor === 'false'  => 0,
                default => $valor,
            };
        }

        if ($normalizados !== []) {
            $this->merge($normalizados);
        }
    }

    /** @return array<string, array<int, mixed>> */
    abstract protected function reglasDeFiltros(): array;

    /**
     * Los filtros que entiende el servicio, ya tipados y sin las claves
     * ausentes: los servicios preguntan con `isset()`, así que una clave en
     * `null` activaría el filtro con un valor vacío.
     *
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        $filtros = array_merge($this->filtrosDeclarados(), [
            'por_pagina' => $this->entero('por_pagina'),
        ]);

        // `!== null` y no `array_filter` a secas: un `estado=false` es un
        // filtro válido —«solo los inactivos»— y el filtro por omisión lo
        // habría descartado junto con los nulos.
        return array_filter($filtros, static fn ($valor) => $valor !== null);
    }

    /** @return array<string, mixed> */
    abstract protected function filtrosDeclarados(): array;

    /**
     * Un entero del filtro, o `null` si no vino.
     *
     * `filled()` y no `has()`: el middleware de Laravel convierte la cadena
     * vacía en null, así que `?puesto_id=` existe y no vale nada.
     */
    protected function entero(string $campo): ?int
    {
        return $this->filled($campo) ? $this->integer($campo) : null;
    }

    /** Un booleano del filtro, o `null` si no vino. */
    protected function booleano(string $campo): ?bool
    {
        return $this->filled($campo) ? $this->boolean($campo) : null;
    }

    /** Una cadena del filtro, o `null` si no vino. */
    protected function cadena(string $campo): ?string
    {
        return $this->filled($campo) ? $this->string($campo)->value() : null;
    }
}
