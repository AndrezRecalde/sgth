<?php

use App\Enums\CausaLicencia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las licencias que añade el contrato colectivo de los obreros (diseño de
 * Acciones de Personal, 9.15; fase 5.3).
 *
 * - `clausulas_de_licencia`: cada cláusula del contrato que toca licencias.
 *   O amplía los días de una causa de la ley (`causa`, y `alcance` si la causa
 *   distingue casos), o crea una causa que la ley no tiene (`nombre`). Lleva
 *   sus días por licencia y, si la cláusula lo dice, un tope por año.
 * - `licencias.clausula_de_licencia_id`: la cláusula que respalda la licencia.
 *   Una pactada no existe sin ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clausulas_de_licencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_colectivo_id')->constrained('contratos_colectivos')->cascadeOnDelete();
            $table->string('clausula', 60);
            $table->string('causa', 40)->nullable();
            $table->string('alcance', 40)->nullable();
            $table->string('nombre', 150)->nullable();
            $table->unsignedSmallInteger('dias');
            $table->unsignedSmallInteger('dias_por_anio')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $legales = implode(', ', array_map(fn (CausaLicencia $c) => "'{$c->value}'", CausaLicencia::legales()));

        DB::statement("ALTER TABLE clausulas_de_licencia ADD CONSTRAINT clausulas_de_licencia_causa_check
            CHECK (causa IS NULL OR causa IN ({$legales}))");
        // O amplía una causa de la ley, o crea una con su nombre: una de las dos.
        DB::statement('ALTER TABLE clausulas_de_licencia ADD CONSTRAINT clausulas_de_licencia_clase_check
            CHECK ((causa IS NULL) <> (nombre IS NULL) AND (causa IS NOT NULL OR alcance IS NULL))');
        DB::statement('ALTER TABLE clausulas_de_licencia ADD CONSTRAINT clausulas_de_licencia_dias_check
            CHECK (dias > 0 AND (dias_por_anio IS NULL OR dias_por_anio >= dias))');
        // Una causa de la ley se amplía una vez por contrato y caso.
        DB::statement("CREATE UNIQUE INDEX clausulas_de_licencia_causa_unica
            ON clausulas_de_licencia (contrato_colectivo_id, causa, COALESCE(alcance, ''))
            WHERE causa IS NOT NULL");

        Schema::table('licencias', function (Blueprint $table) {
            $table->foreignId('clausula_de_licencia_id')->nullable()->after('alcance')
                ->constrained('clausulas_de_licencia')->restrictOnDelete();
        });

        $causas = implode(', ', array_map(fn (string $c) => "'{$c}'", CausaLicencia::valores()));

        DB::statement('ALTER TABLE licencias DROP CONSTRAINT IF EXISTS licencias_causa_check');
        DB::statement("ALTER TABLE licencias ADD CONSTRAINT licencias_causa_check CHECK (causa IN ({$causas}))");
        DB::statement("ALTER TABLE licencias ADD CONSTRAINT licencias_pactada_check
            CHECK (causa <> 'pactada' OR clausula_de_licencia_id IS NOT NULL)");
    }

    public function down(): void
    {
        // Una licencia pactada no se puede expresar sin su cláusula: antes de
        // revertir, hay que anularla y borrarla a mano, no en silencio.
        if (DB::table('licencias')->where('causa', 'pactada')->exists()) {
            throw new RuntimeException('Hay licencias pactadas en el contrato colectivo: no se revierte sin decidir qué hacer con ellas.');
        }

        DB::statement('ALTER TABLE licencias DROP CONSTRAINT IF EXISTS licencias_pactada_check');

        $causas = implode(', ', array_map(fn (CausaLicencia $c) => "'{$c->value}'", CausaLicencia::legales()));
        DB::statement('ALTER TABLE licencias DROP CONSTRAINT IF EXISTS licencias_causa_check');
        DB::statement("ALTER TABLE licencias ADD CONSTRAINT licencias_causa_check CHECK (causa IN ({$causas}))");

        Schema::table('licencias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clausula_de_licencia_id');
        });

        Schema::dropIfExists('clausulas_de_licencia');
    }
};
