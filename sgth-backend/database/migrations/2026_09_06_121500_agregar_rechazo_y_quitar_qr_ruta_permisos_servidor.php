<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El rechazo, que existía a medias, y una columna que nunca dijo la verdad.
     *
     * `EstadoPermiso::RECHAZADO` está declarado en el enum y en el `enum` de la
     * columna desde la primera migración del módulo, y `UpdatePermisoServidorRequest`
     * ya validaba un `observacion_rechazo`. Pero no había ni endpoint que lo
     * asignara ni columnas donde guardar quién rechazó, cuándo y por qué: los
     * otros tres finales del flujo —confirmado, validado, anulado— sí las
     * tienen. Recepción no podía rechazar el documento que le llega adulterado
     * o incompleto; solo podía confirmarlo o dejarlo vencer.
     *
     * `qr_ruta` se va. Guardaba `"qrs/PER-2026-00045.png"`, un archivo que
     * nunca se generó: el QR se dibuja al vuelo dentro del PDF. Ninguna
     * consulta la lee —solo `PermisoService` la escribía— y su valor es
     * literalmente una ruta a un archivo inexistente en todas las filas.
     */
    public function up(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->foreignId('rechazado_por')
                  ->nullable()
                  ->after('validado_ts_en')
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('rechazado_en')->nullable()->after('rechazado_por');
            $table->text('motivo_rechazo')->nullable()->after('rechazado_en');

            $table->dropColumn('qr_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->dropForeign(['rechazado_por']);
            $table->dropColumn(['rechazado_por', 'rechazado_en', 'motivo_rechazo']);

            $table->text('qr_ruta')->nullable()->after('folio');
        });
    }
};
