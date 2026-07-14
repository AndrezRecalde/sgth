<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE convocatorias DROP CONSTRAINT IF EXISTS convocatorias_estado_check');
        DB::statement("ALTER TABLE convocatorias ADD CONSTRAINT convocatorias_estado_check CHECK (estado IN (
            'borrador',
            'publicada',
            'en_evaluacion',
            'en_evaluacion_medica',
            'finalizada',
            'cancelada',
            'desierta'
        ))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE convocatorias DROP CONSTRAINT IF EXISTS convocatorias_estado_check');
        DB::statement("ALTER TABLE convocatorias ADD CONSTRAINT convocatorias_estado_check CHECK (estado IN (
            'borrador',
            'publicada',
            'en_evaluacion',
            'finalizada',
            'cancelada',
            'desierta'
        ))");
    }
};
