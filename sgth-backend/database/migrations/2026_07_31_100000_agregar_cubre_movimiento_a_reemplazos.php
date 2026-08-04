<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enlace de reemplazo: la contratación temporal que cubre el hueco que deja
 * una comisión de servicios o una licencia sin remuneración.
 *
 * Vive en dos lugares a propósito. En la acción de personal porque el enlace
 * se declara al crear el Ingreso y Vinculación del reemplazo, cuando todavía
 * no existe contrato alguno; y en el contrato porque es lo que consulta el
 * control de plazas — sin él habría que remontar a la acción en cada conteo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->foreignId('cubre_movimiento_id')
                ->nullable()
                ->after('movimiento_previo_id')
                ->constrained('movimientos_personal')
                ->nullOnDelete();
        });

        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->foreignId('cubre_movimiento_id')
                ->nullable()
                ->after('puesto_id')
                ->constrained('movimientos_personal')
                ->nullOnDelete();
        });

        // Una ausencia se cubre con un solo reemplazo a la vez. Es un índice
        // parcial: al terminar el reemplazo el contrato deja de ser vigente y
        // la ausencia vuelve a poder cubrirse, que es justo lo que pasa cuando
        // el primer reemplazo renuncia antes de que el titular regrese.
        DB::statement(
            'CREATE UNIQUE INDEX contratos_servidor_cubre_movimiento_unico
             ON contratos_servidor (cubre_movimiento_id)
             WHERE cubre_movimiento_id IS NOT NULL
               AND estado = \'vigente\'
               AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS contratos_servidor_cubre_movimiento_unico');

        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cubre_movimiento_id');
        });

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cubre_movimiento_id');
        });
    }
};
