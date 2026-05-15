<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // En PostgreSQL los enums de Laravel se almacenan
        // como VARCHAR con CHECK constraint.
        // Para agregar un valor nuevo debemos:
        // 1. Eliminar el constraint existente
        // 2. Agregar el nuevo constraint con los valores actualizados

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
                'novedad_contrato'
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
                'egreso'
            ))
        ");
    }
};
