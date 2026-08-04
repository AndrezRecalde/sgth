<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sella quién firmó cada Acción de Personal en el momento de suscribirla.
 *
 * Los datos del firmante se copian —no se referencian— a propósito: el
 * Prefecto y los Directores rotan, y el documento debe seguir mostrando a
 * quien firmó entonces aunque hoy el cargo lo ocupe otra persona o el
 * servidor ya no exista en el sistema. Resolver el firmante al imprimir
 * reescribía la historia en cada reimpresión.
 *
 * Se conserva además el FK al servidor para trazabilidad, pero el PDF se
 * alimenta de las columnas copiadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->date('fecha_suscripcion')->nullable()->after('fecha_registro');

            $table->foreignId('firmante_autoridad_id')
                  ->nullable()->after('fecha_suscripcion')
                  ->constrained('servidores')->nullOnDelete();
            $table->string('firmante_autoridad_nombre', 200)->nullable()->after('firmante_autoridad_id');
            $table->string('firmante_autoridad_cargo', 200)->nullable()->after('firmante_autoridad_nombre');
            $table->string('firmante_autoridad_cedula', 20)->nullable()->after('firmante_autoridad_cargo');

            $table->foreignId('firmante_th_id')
                  ->nullable()->after('firmante_autoridad_cedula')
                  ->constrained('servidores')->nullOnDelete();
            $table->string('firmante_th_nombre', 200)->nullable()->after('firmante_th_id');
            $table->string('firmante_th_cargo', 200)->nullable()->after('firmante_th_nombre');
            $table->string('firmante_th_cedula', 20)->nullable()->after('firmante_th_cargo');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('firmante_autoridad_id');
            $table->dropConstrainedForeignId('firmante_th_id');
            $table->dropColumn([
                'fecha_suscripcion',
                'firmante_autoridad_nombre', 'firmante_autoridad_cargo', 'firmante_autoridad_cedula',
                'firmante_th_nombre', 'firmante_th_cargo', 'firmante_th_cedula',
            ]);
        });
    }
};
