<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE postulantes DROP CONSTRAINT IF EXISTS postulantes_estado_check');
        DB::statement("ALTER TABLE postulantes ADD CONSTRAINT postulantes_estado_check CHECK (estado IN ('inscrito','en_evaluacion','seleccionado','no_seleccionado','lista_espera','aprobado','reprobado','descalificado'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE postulantes DROP CONSTRAINT IF EXISTS postulantes_estado_check');
        DB::statement("ALTER TABLE postulantes ADD CONSTRAINT postulantes_estado_check CHECK (estado IN ('postulado','en_proceso','aprobado','reprobado','descalificado'))");
    }
};
