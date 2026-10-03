<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La cola de Enfermería, «Pendientes de triaje» y los tableros filtran turnos
 * por fecha y estado sin médico de por medio, y el índice que había empieza
 * por `medico_id`. Las atenciones de enfermería se listan por fecha de
 * atención, y las de un familiar se buscan por `carga_familiar_id`; ninguna
 * de las dos columnas tenía índice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendas_medicas', function (Blueprint $table) {
            $table->index(['fecha', 'estado']);
            $table->index('carga_familiar_id');
        });

        Schema::table('atenciones_enfermeria', function (Blueprint $table) {
            $table->index('atendido_en');
            $table->index('carga_familiar_id');
        });
    }

    public function down(): void
    {
        Schema::table('agendas_medicas', function (Blueprint $table) {
            $table->dropIndex(['fecha', 'estado']);
            $table->dropIndex(['carga_familiar_id']);
        });

        Schema::table('atenciones_enfermeria', function (Blueprint $table) {
            $table->dropIndex(['atendido_en']);
            $table->dropIndex(['carga_familiar_id']);
        });
    }
};
