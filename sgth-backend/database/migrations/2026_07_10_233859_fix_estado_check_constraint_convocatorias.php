<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE convocatorias DROP CONSTRAINT IF EXISTS convocatorias_estado_check');
        DB::statement("ALTER TABLE convocatorias ADD CONSTRAINT convocatorias_estado_check CHECK (estado IN ('borrador','publicada','en_evaluacion','finalizada','cancelada','desierta'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE convocatorias DROP CONSTRAINT IF EXISTS convocatorias_estado_check');
        DB::statement("ALTER TABLE convocatorias ADD CONSTRAINT convocatorias_estado_check CHECK (estado IN ('publicada','en_evaluacion','finalizada','cancelada'))");
    }
};
