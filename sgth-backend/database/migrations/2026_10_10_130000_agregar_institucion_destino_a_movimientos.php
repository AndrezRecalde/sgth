<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dónde va el servidor y para qué (diseño de Acciones de Personal, 4.2 y
 * 8.1; fase 2.3).
 *
 * - `institucion_destino`: la comisión de servicios es servir en otra entidad
 *   del Estado, y el intercambio voluntario es entre instituciones (LOSEP 30,
 *   31 y 39). Hasta aquí la institución iba, si iba, en la explicación.
 * - `para_estudios_o_eventos`: la comisión con remuneración para estudios o
 *   eventos obliga a servir al volver un tiempo igual al de la comisión
 *   (LOSEP 30), y el documento tiene que decirlo.
 *
 * Las comisiones ya registradas se quedan sin institución: está en su
 * explicación, que no se reescribe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('institucion_destino', 255)->nullable()->after('unidad_destino_id');
            $table->boolean('para_estudios_o_eventos')->default(false)->after('institucion_destino');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn(['institucion_destino', 'para_estudios_o_eventos']);
        });
    }
};
