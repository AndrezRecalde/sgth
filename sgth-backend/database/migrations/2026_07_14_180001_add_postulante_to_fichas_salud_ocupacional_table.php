<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->foreignId('postulante_id')
                ->nullable()
                ->after('servidor_id')
                ->constrained('postulantes')
                ->nullOnDelete();
        });

        // Un candidato de INGRESO todavía no tiene registro en `servidores`
        // (se crea recién al confirmar incorporación, después del FEMO).
        DB::statement('ALTER TABLE fichas_salud_ocupacional ALTER COLUMN servidor_id DROP NOT NULL');

        DB::statement('ALTER TABLE fichas_salud_ocupacional DROP CONSTRAINT IF EXISTS chk_ficha_persona');
        DB::statement('ALTER TABLE fichas_salud_ocupacional ADD CONSTRAINT chk_ficha_persona CHECK (
            servidor_id IS NOT NULL OR postulante_id IS NOT NULL
        )');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fichas_salud_ocupacional DROP CONSTRAINT IF EXISTS chk_ficha_persona');
        DB::statement('ALTER TABLE fichas_salud_ocupacional ALTER COLUMN servidor_id SET NOT NULL');

        Schema::table('fichas_salud_ocupacional', function (Blueprint $table) {
            $table->dropConstrainedForeignId('postulante_id');
        });
    }
};
