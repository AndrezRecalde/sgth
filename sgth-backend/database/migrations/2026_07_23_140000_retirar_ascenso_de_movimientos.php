<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Confirmado con Talento Humano/UATH: 'ascenso' no existe como acción de
 * personal en la operación real del GAD (cero registros en producción) y
 * no tiene un mecanismo formal de registro distinto de traslado/cambio
 * administrativo. Se retira del catálogo de tipo_movimiento.
 *
 * Nota: 'ascenso' también estaba mapeado como uno de los 4 tipos
 * reportables al SIITH (ConfiguracionReporteMovimientoSeeder). Ese mapeo
 * se reduce a 3 confirmados (ingreso, traslado, cambio_administrativo) —
 * la categoría "ascensos" de la norma queda sin tipo_movimiento que la
 * cubra hasta que se defina cómo se registrará esa acción de personal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE movimientos_personal
            DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check
        ");

        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_tipo_movimiento_check
            CHECK (tipo_movimiento IN (
                'traslado',
                'subrogacion',
                'comision_servicios',
                'cambio_regimen',
                'cambio_puesto',
                'ingreso',
                'egreso',
                'novedad_contrato',
                'cambio_denominacion',
                'prestacion_servicios',
                'cambio_administrativo',
                'comision_sin_remuneracion',
                'licencia_sin_remuneracion',
                'incremento_remuneracion',
                'traspaso',
                'destitucion'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE movimientos_personal
            DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check
        ");

        DB::statement("
            ALTER TABLE movimientos_personal
            ADD CONSTRAINT movimientos_personal_tipo_movimiento_check
            CHECK (tipo_movimiento IN (
                'traslado',
                'ascenso',
                'subrogacion',
                'comision_servicios',
                'cambio_regimen',
                'cambio_puesto',
                'ingreso',
                'egreso',
                'novedad_contrato',
                'cambio_denominacion',
                'prestacion_servicios',
                'cambio_administrativo',
                'comision_sin_remuneracion',
                'licencia_sin_remuneracion',
                'incremento_remuneracion',
                'traspaso',
                'destitucion'
            ))
        ");
    }
};
