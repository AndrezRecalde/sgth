<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Por qué se cerró un concurso sin ganadores (2026-10-05). Antes el estado se
 * cambiaba con el PATCH de la convocatoria, a cualquier valor y sin dejar
 * constancia; ahora declararlo desierto o cancelarlo es una acción propia y
 * exige el motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->text('motivo_cierre')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropColumn('motivo_cierre');
        });
    }
};
