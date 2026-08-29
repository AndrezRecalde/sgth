<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sección O del formulario SNS-MSP/HCU-form.123/2025 — datos del profesional.
 *
 * El impreso pide el código médico junto al nombre de quien evalúa. Es un
 * atributo de la persona —su registro profesional ante el ACESS— y no del
 * usuario del sistema ni de cada ficha, así que vive en `servidores`.
 *
 * La ficha lo lee del evaluador al imprimirse. No se copia en la ficha porque,
 * a diferencia del nombre del puesto, el código de un profesional no cambia:
 * identifica a la persona de por vida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('codigo_medico', 30)
                ->nullable()
                ->after('correo_personal');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn('codigo_medico');
        });
    }
};
