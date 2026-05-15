<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('tipo_movimiento')->change();
        });

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->enum('tipo_movimiento', [
                'traslado',
                'ascenso',
                'subrogacion',
                'comision_servicios',
                'cambio_regimen',
                'cambio_puesto',
                'ingreso',
                'egreso',
                'novedad_contrato'
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('tipo_movimiento')->change();
        });

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->enum('tipo_movimiento', [
                'traslado',
                'ascenso',
                'subrogacion',
                'comision_servicios',
                'cambio_regimen',
                'cambio_puesto',
                'ingreso',
                'egreso'
            ])->change();
        });
    }
};
