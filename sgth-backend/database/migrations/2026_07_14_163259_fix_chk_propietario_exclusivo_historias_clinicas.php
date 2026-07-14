<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE historias_clinicas DROP CONSTRAINT IF EXISTS chk_propietario_exclusivo');
        DB::statement("ALTER TABLE historias_clinicas ADD CONSTRAINT chk_propietario_exclusivo CHECK (
            servidor_id IS NOT NULL
            OR carga_familiar_id IS NOT NULL
            OR tipo_paciente = 'candidato'
        )");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE historias_clinicas DROP CONSTRAINT IF EXISTS chk_propietario_exclusivo');
        DB::statement("ALTER TABLE historias_clinicas ADD CONSTRAINT chk_propietario_exclusivo CHECK (
            servidor_id IS NOT NULL
            OR carga_familiar_id IS NOT NULL
        )");
    }
};
