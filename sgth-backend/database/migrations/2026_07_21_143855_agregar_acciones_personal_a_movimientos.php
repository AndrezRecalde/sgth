<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->date('fecha_inicio')->nullable()->after('fecha_efectiva');
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');
        });

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
                'novedad_contrato'
            ))
        ");

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio', 'fecha_fin']);
        });
    }
};
