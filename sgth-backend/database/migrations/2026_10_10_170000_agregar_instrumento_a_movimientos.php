<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El instrumento del acto (diseño de Acciones de Personal, 8.1; TH N7; fase
 * 2.7): con qué documento se formaliza. Llega con el primero distinto de la
 * acción de personal, el registro del contrato de un ocasional.
 *
 * - `instrumento`, `accion_personal` para todo lo que ya existe: lo emitido
 *   conserva su número AP (4.5).
 * - Los borradores de ingreso de ocasionales pasan a registro del contrato:
 *   todavía no se firmaron. Los suscritos se quedan como acción.
 * - El número del registro es el del contrato, así que el índice único pasa a
 *   ser por instrumento —cada uno tiene su serie— y la columna admite el largo
 *   de un número de contrato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('instrumento', 30)->default('accion_personal')->after('clase');
            $table->dropUnique('movimientos_personal_codigo_registro_unique');
        });

        DB::statement('ALTER TABLE movimientos_personal ALTER COLUMN codigo_registro TYPE varchar(100)');

        DB::statement("
            ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_instrumento_check
            CHECK (instrumento IN ('accion_personal', 'registro_contrato'))
        ");

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->unique(['instrumento', 'codigo_registro'], 'movimientos_personal_instrumento_codigo_unique');
        });

        DB::table('movimientos_personal')
            ->where('tipo_movimiento', 'ingreso')
            ->where('tipo_nombramiento_propuesto', 'servicios_ocasionales')
            ->where('estado', 'borrador')
            ->update(['instrumento' => 'registro_contrato']);
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropUnique('movimientos_personal_instrumento_codigo_unique');
        });

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_instrumento_check');

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn('instrumento');
        });

        DB::statement('ALTER TABLE movimientos_personal ALTER COLUMN codigo_registro TYPE varchar(30)');

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->unique('codigo_registro');
        });
    }
};
