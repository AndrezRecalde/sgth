<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La observación de una solicitud de vacaciones.
 *
 * El formulario la pedía, la API la validaba (hasta 500 caracteres) y el PDF
 * tenía su recuadro, pero la tabla nunca tuvo la columna: se descartaba sin
 * aviso y el recuadro salía siempre con «—».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->text('observacion')->nullable()->after('persona_reemplaza_id');
        });
    }

    public function down(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropColumn('observacion');
        });
    }
};
