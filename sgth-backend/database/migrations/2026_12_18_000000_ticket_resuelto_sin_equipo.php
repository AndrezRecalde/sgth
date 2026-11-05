<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por qué un ticket de hardware se resolvió sin un equipo del inventario
 * (2026-11-05): una laptop personal, un mouse sin código, un equipo de otra
 * entidad. Al resolver uno de Hardware o Impresoras, el técnico indica el
 * equipo atendido o marca que no es del inventario y dice por qué; así no
 * elige cualquiera para poder cerrar, y el jefe de TIC ve cuántos y por qué.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'motivo_sin_equipo')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->string('motivo_sin_equipo', 300)->nullable()->after('bien_informatico_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('motivo_sin_equipo');
        });
    }
};
