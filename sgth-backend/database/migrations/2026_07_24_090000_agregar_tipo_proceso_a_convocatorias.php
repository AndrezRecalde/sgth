<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 'tipo_proceso' distingue un concurso formal (méritos y oposición, con
 * plazo real) de un proceso "Reclutamiento Express" — sin default a
 * propósito, para forzar una decisión explícita en cada convocatoria
 * nueva. 'tipo_nombramiento_previsto' solo aplica a express: el tipo que
 * Talento Humano declara al abrir el proceso, restringido a los 4 tipos
 * que tienen sentido fuera de un concurso formal (nunca Permanente, Libre
 * Nombramiento ni Elección Popular).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            $table->string('tipo_proceso', 20)->nullable()->after('tipo');
            $table->string('tipo_nombramiento_previsto', 50)->nullable()->after('tipo_proceso');
        });

        // Backfill: toda convocatoria que ya existe hoy es un concurso
        // formal — 'express' es un concepto nuevo que no existía antes.
        DB::table('convocatorias')->update(['tipo_proceso' => 'formal']);

        Schema::table('convocatorias', function (Blueprint $table) {
            $table->string('tipo_proceso', 20)->nullable(false)->change();
        });

        DB::statement("
            ALTER TABLE convocatorias ADD CONSTRAINT convocatorias_tipo_proceso_check
            CHECK (tipo_proceso IN ('formal', 'express'))
        ");

        DB::statement("
            ALTER TABLE convocatorias ADD CONSTRAINT convocatorias_tipo_nombramiento_previsto_check
            CHECK (tipo_nombramiento_previsto IS NULL OR tipo_nombramiento_previsto IN (
                'nombramiento_provisional', 'servicios_ocasionales',
                'servicios_profesionales', 'codigo_trabajo'
            ))
        ");

        // Coherencia cruzada a nivel de BD: formal nunca lleva tipo
        // previsto, express siempre lo exige.
        DB::statement("
            ALTER TABLE convocatorias ADD CONSTRAINT convocatorias_proceso_nombramiento_coherencia_check
            CHECK (
                (tipo_proceso = 'formal'  AND tipo_nombramiento_previsto IS NULL)
                OR
                (tipo_proceso = 'express' AND tipo_nombramiento_previsto IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE convocatorias DROP CONSTRAINT IF EXISTS convocatorias_proceso_nombramiento_coherencia_check');
        DB::statement('ALTER TABLE convocatorias DROP CONSTRAINT IF EXISTS convocatorias_tipo_nombramiento_previsto_check');
        DB::statement('ALTER TABLE convocatorias DROP CONSTRAINT IF EXISTS convocatorias_tipo_proceso_check');

        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropColumn(['tipo_proceso', 'tipo_nombramiento_previsto']);
        });
    }
};
