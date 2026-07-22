<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE contratos_servidor
            DROP CONSTRAINT IF EXISTS contratos_servidor_tipo_nombramiento_check
        ");

        DB::statement("
            ALTER TABLE contratos_servidor
            ADD CONSTRAINT contratos_servidor_tipo_nombramiento_check
            CHECK (tipo_nombramiento IN (
                'nombramiento_permanente',
                'nombramiento_provisional',
                'servicios_ocasionales',
                'libre_nombramiento_remocion',
                'codigo_trabajo',
                'servicios_profesionales',
                'eleccion_popular'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE contratos_servidor
            DROP CONSTRAINT IF EXISTS contratos_servidor_tipo_nombramiento_check
        ");

        DB::statement("
            ALTER TABLE contratos_servidor
            ADD CONSTRAINT contratos_servidor_tipo_nombramiento_check
            CHECK (tipo_nombramiento IN (
                'nombramiento_permanente',
                'nombramiento_provisional',
                'servicios_ocasionales',
                'libre_nombramiento_remocion',
                'codigo_trabajo',
                'servicios_profesionales'
            ))
        ");
    }
};
