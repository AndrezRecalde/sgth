<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subrogación y encargo, cada uno con su regla (diseño de Acciones de
 * Personal, 4.2 y 9.5; fase 5.2).
 *
 * - `fecha_fin` admite vacío, solo en el encargo: dura «hasta la designación
 *   del titular» (LOSEP, Art. 127). La subrogación sigue con su fin.
 * - `ausencia_tipo` y `ausencia_id`: la ausencia del titular que motiva la
 *   subrogación —vacaciones, licencia, reposo o acción de personal—, o «otra»,
 *   que no está en el SGTH y se respalda con la resolución. Sin clave foránea:
 *   apunta a tablas distintas según el tipo.
 */
return new class extends Migration
{
    private const TIPOS = ['vacacion', 'licencia', 'reposo', 'accion_personal', 'otra'];

    public function up(): void
    {
        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->date('fecha_fin')->nullable()->change();
            $table->string('ausencia_tipo', 20)->nullable();
            $table->unsignedBigInteger('ausencia_id')->nullable();

            $table->index(['ausencia_tipo', 'ausencia_id']);
        });

        $tipos = implode(', ', array_map(fn (string $t) => "'{$t}'", self::TIPOS));

        DB::statement("ALTER TABLE subrogaciones ADD CONSTRAINT subrogaciones_ausencia_tipo_check
            CHECK (ausencia_tipo IS NULL OR ausencia_tipo IN ({$tipos}))");
        DB::statement("ALTER TABLE subrogaciones ADD CONSTRAINT subrogaciones_fin_check
            CHECK (tipo = 'encargo' OR fecha_fin IS NOT NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subrogaciones DROP CONSTRAINT IF EXISTS subrogaciones_fin_check');
        DB::statement('ALTER TABLE subrogaciones DROP CONSTRAINT IF EXISTS subrogaciones_ausencia_tipo_check');

        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->dropIndex(['ausencia_tipo', 'ausencia_id']);
            $table->dropColumn(['ausencia_tipo', 'ausencia_id']);
        });

        // Lo que quedó sin fin se cierra hoy: la columna vuelve a ser obligatoria.
        DB::table('subrogaciones')->whereNull('fecha_fin')->update(['fecha_fin' => now()->toDateString()]);

        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->date('fecha_fin')->nullable(false)->change();
        });
    }
};
