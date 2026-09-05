<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticoCie10 extends Model
{
    use HasFactory;

    protected $table = 'diagnosticos_cie10';

    protected $fillable = [
        'codigo',
        'descripcion',
        'categoria',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Busca por código o descripción como se teclea de verdad.
     *
     * La versión anterior comparaba el término entero contra el texto tal cual,
     * y eso dejaba fuera casi todo lo que un médico escribe con el paciente
     * delante:
     *
     * - Sin tildes no había nada que hacer. «migrana» devolvía cero, y 2325 de
     *   las 8918 descripciones del catálogo llevan tilde o eñe. El desplegable
     *   decía «Sin resultados», que se lee como «ese diagnóstico no existe».
     * - El término tenía que aparecer seguido y en ese orden: «diabetes 2» no
     *   encontraba «DIABETES MELLITUS TIPO 2», y «aguda faringitis» tampoco
     *   encontraba la faringitis aguda.
     *
     * Ahora cada palabra se busca por separado y todas tienen que aparecer, en
     * el código o en la descripción, sin importar el orden ni las tildes.
     */
    public function scopeBuscar(Builder $query, string $termino): Builder
    {
        $palabras = preg_split(
            '/\s+/', trim($termino), -1, PREG_SPLIT_NO_EMPTY
        ) ?: [];

        foreach ($palabras as $palabra) {
            $patron = '%' . $palabra . '%';

            $query->where(function (Builder $q) use ($patron) {
                $q->whereRaw('unaccent(codigo) ILIKE unaccent(?)', [$patron])
                  ->orWhereRaw(
                      'unaccent(descripcion) ILIKE unaccent(?)', [$patron]
                  );
            });
        }

        return $query;
    }

    /**
     * Lo más parecido a lo que se buscó, primero.
     *
     * Antes no había orden ninguno: con 8918 filas y un tope de 20, quien
     * escribía «infeccion» —84 coincidencias— recibía veinte cualesquiera. El
     * código exacto va primero, luego lo que empieza por el término, y el resto
     * después.
     */
    public function scopeOrdenadoPorParecido(
        Builder $query,
        string $termino
    ): Builder {
        $limpio  = trim($termino);
        $prefijo = $limpio . '%';

        return $query->orderByRaw(
            'CASE
                WHEN UPPER(codigo) = UPPER(?) THEN 0
                WHEN codigo ILIKE ? THEN 1
                WHEN unaccent(descripcion) ILIKE unaccent(?) THEN 2
                ELSE 3
             END, codigo',
            [$limpio, $prefijo, $prefijo]
        );
    }
}
