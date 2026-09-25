<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un único total institucional de horas trabajadas por período.
 *
 * `horas_trabajadas_periodo` ya tenía `unique(['periodo',
 * 'unidad_administrativa_id'])`, pero en PostgreSQL —y en el estándar SQL— dos
 * NULL no son iguales entre sí, así que ese índice no impide dos filas con el
 * mismo período y la unidad en NULL: justo las del total institucional, que es
 * el que manda sobre las unidades al calcular los índices del CD 513.
 *
 * Hasta ahora no se notaba porque el servicio hacía `updateOrCreate`, que
 * encontraba la fila existente y la pisaba. Al pasar a rechazar el duplicado,
 * la comprobación vive en PHP: entre el SELECT y el INSERT hay un hueco, y dos
 * peticiones a la vez pueden colarse las dos. Con dos totales institucionales
 * del mismo período, `HorasTrabajadas` no tiene forma de saber cuál manda.
 *
 * Se resuelve con un índice único parcial en vez de `NULLS NOT DISTINCT`
 * (PostgreSQL 15+): el parcial funciona en cualquier versión y dice
 * exactamente lo que se quiere decir —entre las filas sin unidad, el período no
 * se repite—.
 */
return new class extends Migration
{
    private const INDICE = 'horas_trabajadas_periodo_institucional_unique';

    public function up(): void
    {
        // Si ya hubiera duplicados, PostgreSQL rechaza la creación del índice
        // con un 23505 que no dice cuáles son. Se comprueba antes para que el
        // fallo nombre los períodos que hay que limpiar a mano: borrar filas
        // aquí sería decidir por quien las cargó cuál de las dos cifras vale.
        $duplicados = DB::table('horas_trabajadas_periodo')
            ->select('periodo')
            ->whereNull('unidad_administrativa_id')
            ->groupBy('periodo')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('periodo');

        if ($duplicados->isNotEmpty()) {
            throw new RuntimeException(
                'No se puede crear el índice: estos períodos ya tienen más de un total '
                .'institucional de horas trabajadas: '.$duplicados->implode(', ')
                .'. Deje una sola fila por período (la cifra correcta la decide quien las cargó) '
                .'y vuelva a ejecutar la migración.'
            );
        }

        DB::statement(
            'CREATE UNIQUE INDEX '.self::INDICE.' ON horas_trabajadas_periodo (periodo) '
            .'WHERE unidad_administrativa_id IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS '.self::INDICE);
    }
};
