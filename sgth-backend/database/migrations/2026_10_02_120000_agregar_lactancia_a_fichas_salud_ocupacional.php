<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lactancia como quinto grupo de atención prioritaria (sección A).
 *
 * El impreso del MSP en blanco (SNS-MSP/HCU-form.123/2025) trae cuatro
 * grupos; el formato que el Dispensario del GADPE imprime y archiva añade
 * «lactancia» entre enfermedad catastrófica y adulto mayor. Decidido con el
 * usuario el 2026-10-02: se agrega, y solo aplica a pacientes mujeres, igual
 * que «embarazada».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->boolean('grupo_lactancia')
                ->default(false)
                ->after('grupo_enfermedad_catastrofica');
        });
    }

    public function down(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->dropColumn('grupo_lactancia');
        });
    }
};
