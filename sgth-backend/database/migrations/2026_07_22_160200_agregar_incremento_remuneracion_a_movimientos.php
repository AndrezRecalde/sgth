<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
                'incremento_remuneracion'
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
                'licencia_sin_remuneracion'
            ))
        ");
    }
};
