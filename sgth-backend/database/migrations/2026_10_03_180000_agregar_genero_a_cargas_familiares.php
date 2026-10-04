<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El sexo de las cargas familiares. No constaba: los reportes del Dispensario
 * (morbilidad por sexo, registro de atenciones) ponían «Sin dato» a todos los
 * familiares, que son buena parte de los pacientes.
 *
 * Misma columna y mismos valores que `servidores.genero`, para que el
 * Dispensario lea a uno y otro igual. Nula: los familiares ya registrados no
 * lo tienen y se completa al editarlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cargas_familiares', function (Blueprint $table) {
            $table->string('genero', 20)->nullable()->after('fecha_nacimiento');
        });
    }

    public function down(): void
    {
        Schema::table('cargas_familiares', function (Blueprint $table) {
            $table->dropColumn('genero');
        });
    }
};
