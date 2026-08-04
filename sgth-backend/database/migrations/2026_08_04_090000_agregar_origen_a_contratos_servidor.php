<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De dónde nació cada vínculo laboral.
 *
 * Lo normal es que nazca de una Acción de Personal de ingreso, que es la regla
 * que el sistema hace cumplir. La excepción es la carga inicial: los servidores
 * que ya estaban vinculados antes de que el sistema existiera no tienen —ni
 * pueden tener— una acción que los respalde, porque el acto ocurrió en papel.
 *
 * Marcarlo permite responder en cualquier momento "¿qué vínculos entraron por
 * migración y cuáles por el flujo formal?", que es justo lo que se pierde
 * cuando se abre una puerta de carga masiva sin dejar rastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->string('origen', 30)
                ->default('accion_personal')
                ->after('estado');
        });

        // Lo ya existente nació del flujo formal: es el default, pero se deja
        // explícito para no depender de él en una lectura futura.
        DB::table('contratos_servidor')->update(['origen' => 'accion_personal']);
    }

    public function down(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
