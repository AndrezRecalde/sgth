<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar constraint antiguo
        DB::statement('
            ALTER TABLE viaticos
            DROP CONSTRAINT viaticos_estado_check
        ');

        // Crear constraint actualizado con los 7 estados
        DB::statement("
            ALTER TABLE viaticos
            ADD CONSTRAINT viaticos_estado_check
            CHECK (estado IN (
                'solicitado',
                'aprobado',
                'con_anticipo',
                'en_comision',
                'pendiente_liquidacion',
                'liquidado',
                'contabilizado'
            ))
        ");
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE viaticos
            DROP CONSTRAINT viaticos_estado_check
        ');

        DB::statement("
            ALTER TABLE viaticos
            ADD CONSTRAINT viaticos_estado_check
            CHECK (estado IN (
                'solicitado',
                'aprobado_jefe',
                'aprobado_director',
                'aprobado_autoridad',
                'aprobado_uath',
                'aprobado_financiero',
                'con_anticipo',
                'en_comision',
                'pendiente_liquidacion',
                'liquidado',
                'contabilizado'
            ))
        ");
    }
};
