<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las sanciones de los obreros con el reglamento interno (diseño de Acciones
 * de Personal, 9.15; fase 4.4). El Código del Trabajo prohíbe imponer multas
 * que no prevea el reglamento interno aprobado (Art. 44 a), y el sumario
 * administrativo es el procedimiento de la LOSEP, no el suyo.
 *
 * - `faltas_reglamento_interno`: el catálogo que Talento Humano carga con su
 *   copia del reglamento. Cada falta dice qué sanciones admite y hasta qué
 *   porcentaje la multa, nunca más del 10 %.
 * - `sumarios.procedimiento`: el sumario administrativo de la LOSEP, o el
 *   procedimiento del reglamento interno de un obrero, con la falta imputada.
 * - La sanción del reglamento interno guarda su falta del catálogo, y la
 *   gravedad de la LOSEP queda vacía: no le aplica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faltas_reglamento_interno', function (Blueprint $table) {
            $table->id();
            $table->string('articulo', 100);
            $table->text('descripcion');
            $table->string('gravedad', 40)->nullable();
            $table->boolean('admite_amonestacion_verbal')->default(false);
            $table->boolean('admite_amonestacion_escrita')->default(false);
            $table->boolean('admite_multa')->default(false);
            $table->decimal('porcentaje_maximo_multa', 5, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE faltas_reglamento_interno ADD CONSTRAINT faltas_reglamento_interno_sancion_check CHECK (
                (admite_amonestacion_verbal OR admite_amonestacion_escrita OR admite_multa)
                AND (NOT admite_multa OR (porcentaje_maximo_multa > 0 AND porcentaje_maximo_multa <= 10))
            )
        ");

        Schema::table('sumarios', function (Blueprint $table) {
            $table->string('procedimiento', 30)->default('sumario_administrativo')->after('servidor_id');
            $table->foreignId('falta_reglamento_id')->nullable()->after('procedimiento')
                ->constrained('faltas_reglamento_interno')->restrictOnDelete();
        });

        DB::statement("
            ALTER TABLE sumarios ADD CONSTRAINT sumarios_procedimiento_check CHECK (
                procedimiento IN ('sumario_administrativo', 'reglamento_interno')
                AND (procedimiento = 'sumario_administrativo' OR falta_reglamento_id IS NOT NULL)
            )
        ");

        Schema::table('sanciones_disciplinarias', function (Blueprint $table) {
            $table->foreignId('falta_reglamento_id')->nullable()->after('sumario_id')
                ->constrained('faltas_reglamento_interno')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE sanciones_disciplinarias ALTER COLUMN tipo_falta DROP NOT NULL');
        DB::statement('
            ALTER TABLE sanciones_disciplinarias ADD CONSTRAINT sanciones_disciplinarias_falta_check
            CHECK (tipo_falta IS NOT NULL OR falta_reglamento_id IS NOT NULL)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sanciones_disciplinarias DROP CONSTRAINT IF EXISTS sanciones_disciplinarias_falta_check');
        DB::table('sanciones_disciplinarias')->whereNull('tipo_falta')->update(['tipo_falta' => 'leve']);
        DB::statement('ALTER TABLE sanciones_disciplinarias ALTER COLUMN tipo_falta SET NOT NULL');

        Schema::table('sanciones_disciplinarias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('falta_reglamento_id');
        });

        DB::statement('ALTER TABLE sumarios DROP CONSTRAINT IF EXISTS sumarios_procedimiento_check');
        Schema::table('sumarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('falta_reglamento_id');
            $table->dropColumn('procedimiento');
        });

        Schema::dropIfExists('faltas_reglamento_interno');
    }
};
