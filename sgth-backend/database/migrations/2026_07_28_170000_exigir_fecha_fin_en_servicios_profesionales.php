<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un contrato de Servicios Profesionales sin fecha_fin nunca vence, y por lo
 * tanto la tarea que detecta contratos vencidos lo ignora en silencio: el
 * servidor queda vinculado indefinidamente sin que nadie se entere.
 *
 * ContratoServidorService::crear() ya deriva la fecha (31 de diciembre del año
 * de inicio) y es el único punto de la aplicación que crea contratos, así que
 * por la app no puede entrar una fila sin vencimiento. Este CHECK cubre lo que
 * queda fuera: cargas históricas por SQL, seeders y migraciones de datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill con la misma regla que aplica el servicio: el plazo termina
        // el 31 de diciembre del año en que empezó el contrato.
        $actualizadas = DB::update("
            UPDATE contratos_servidor
            SET fecha_fin = make_date(EXTRACT(YEAR FROM fecha_inicio)::int, 12, 31)
            WHERE tipo_nombramiento = 'servicios_profesionales'
              AND fecha_fin IS NULL
        ");

        if ($actualizadas > 0) {
            echo "  Backfill: {$actualizadas} contrato(s) de Servicios Profesionales recibieron fecha de vencimiento.\n";
        }

        DB::statement("
            ALTER TABLE contratos_servidor
            ADD CONSTRAINT contratos_servicios_profesionales_con_vencimiento_check
            CHECK (
                tipo_nombramiento <> 'servicios_profesionales'
                OR fecha_fin IS NOT NULL
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE contratos_servidor
            DROP CONSTRAINT IF EXISTS contratos_servicios_profesionales_con_vencimiento_check
        ");
    }
};
