<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por qué se anuló un permiso.
 *
 * Rechazar y revertir ya exigían motivo; anular no, así que de un permiso
 * anulado quedaba quién y cuándo, pero no por qué. Va en su propia columna y no
 * en `motivo_rechazo`: son dos cosas distintas, y un permiso puede haber sido
 * rechazado, revertido y después anulado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->text('motivo_anulacion')->nullable()->after('anulado_en');
        });
    }

    public function down(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->dropColumn('motivo_anulacion');
        });
    }
};
