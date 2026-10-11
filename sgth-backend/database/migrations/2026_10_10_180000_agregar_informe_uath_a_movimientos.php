<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El informe técnico de la UATH que el traslado exige (LOSEP Art. 35; diseño de
 * Acciones de Personal, 4.2; fase 3.1). Es también donde consta que el servidor
 * cumple el perfil del puesto (Art. 36), que el sistema no tiene cómo
 * comprobar. Los traslados ya registrados se quedan sin él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('informe_uath_numero', 100)->nullable()->after('resolucion_numero');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn('informe_uath_numero');
        });
    }
};
