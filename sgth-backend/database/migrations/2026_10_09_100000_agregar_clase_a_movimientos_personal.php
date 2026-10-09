<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La clase legal de cada acción de personal (diseño de Acciones de Personal,
 * fase 1.1): el acto con el nombre que le da la LOSEP —traslado, intercambio
 * voluntario, cambio de ocupación…—, que es lo que el catálogo y la pantalla
 * usan desde ahora.
 *
 * Se agrega junto al par tipo/subtipo, que sigue mandando sobre cómo opera cada
 * acción. La clase se deriva de él (`ClaseAccionPersonal::desde()`) y no lo
 * reemplaza todavía.
 *
 * Null en la bitácora del expediente —novedad de contrato, cambio de puesto,
 * cambio de régimen, egreso—, que no es un acto.
 */
return new class extends Migration
{
    private const CLASES = [
        'ingreso',
        'traslado',
        'intercambio_voluntario',
        'comision_con_remuneracion',
        'comision_sin_remuneracion',
        'licencia_sin_remuneracion',
        'subrogacion',
        'encargo',
        'incremento_remuneracion',
        'cambio_ocupacion',
        'sancion',
        'cesacion',
    ];

    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('clase', 40)->nullable()->after('subtipo_movimiento');
            $table->index('clase');
        });

        $lista = implode(', ', array_map(fn (string $c) => "'{$c}'", self::CLASES));

        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_clase_check
            CHECK (clase IS NULL OR clase IN ({$lista}))
        ");

        $this->rellenarClase();
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_clase_check');

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropIndex(['clase']);
            $table->dropColumn('clase');
        });
    }

    /**
     * La misma correspondencia que `ClaseAccionPersonal::desde()`, escrita en
     * SQL para que esta migración no cambie de significado si el enum cambia.
     *
     * Primero el subtipo, que es el que manda donde existe; luego los tipos sin
     * subtipo, incluidos los planos anteriores a la taxonomía de dos niveles,
     * que se resuelven por su subtipo equivalente. La subrogación se separa del
     * encargo mirando su fila en `subrogaciones`, que es la única que lo dice.
     *
     * Toca filas ya registradas a propósito: la guarda de inmutabilidad vive en
     * el modelo, y aquí no se reescribe el acto —se le pone nombre—.
     */
    private function rellenarClase(): void
    {
        DB::statement("
            UPDATE movimientos_personal AS m
            SET clase = CASE
                WHEN m.subtipo_movimiento = 'traspaso'                  THEN 'traslado'
                WHEN m.subtipo_movimiento = 'traslado_administrativo'   THEN 'intercambio_voluntario'
                WHEN m.subtipo_movimiento = 'comision_con_remuneracion' THEN 'comision_con_remuneracion'
                WHEN m.subtipo_movimiento = 'comision_sin_remuneracion' THEN 'comision_sin_remuneracion'
                WHEN m.subtipo_movimiento = 'sancion_disciplinaria'     THEN 'sancion'
                WHEN m.subtipo_movimiento IN (
                    'renuncia', 'destitucion', 'jubilacion', 'incapacidad',
                    'contrato_finalizado', 'visto_bueno'
                )                                                        THEN 'cesacion'

                WHEN m.tipo_movimiento = 'ingreso'                      THEN 'ingreso'
                WHEN m.tipo_movimiento IN ('prestacion_servicios', 'traspaso') THEN 'traslado'
                WHEN m.tipo_movimiento = 'traslado'                     THEN 'intercambio_voluntario'
                WHEN m.tipo_movimiento = 'comision_servicios'           THEN 'comision_con_remuneracion'
                WHEN m.tipo_movimiento = 'comision_sin_remuneracion'    THEN 'comision_sin_remuneracion'
                WHEN m.tipo_movimiento = 'licencia_sin_remuneracion'    THEN 'licencia_sin_remuneracion'
                WHEN m.tipo_movimiento = 'incremento_remuneracion'      THEN 'incremento_remuneracion'
                WHEN m.tipo_movimiento = 'cambio_denominacion'          THEN 'cambio_ocupacion'
                WHEN m.tipo_movimiento = 'regimen_disciplinario'        THEN 'sancion'
                WHEN m.tipo_movimiento IN ('cesacion_funciones', 'destitucion') THEN 'cesacion'
                WHEN m.tipo_movimiento = 'subrogacion' THEN COALESCE(
                    (SELECT s.tipo FROM subrogaciones AS s
                      WHERE s.movimiento_personal_id = m.id
                      ORDER BY s.id
                      LIMIT 1),
                    'subrogacion'
                )
                ELSE NULL
            END
        ");
    }
};
