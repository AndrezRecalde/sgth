<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una solicitud de certificación médica pedida por error no tenía vuelta atrás.
 *
 * El enum de `estado` admite `cancelada` desde que se creó la tabla, pero nada
 * en la aplicación la escribía nunca: los dos selectores de estado la ofrecían
 * como filtro de un estado que no podía existir.
 *
 * Y el hueco era real. Un lote mal lanzado —cuarenta servidores con el tipo de
 * evento equivocado— se quedaba «pendiente» para siempre, en rojo por vencido,
 * en la bandeja de quien evalúa; y `storeLote` omite a quien ya tiene una
 * solicitud activa, así que tampoco se podía relanzar la correcta.
 *
 * Se cancela marcando, como la anulación de `certificados_medicos`: quién,
 * cuándo y por qué, con la fila intacta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_certificacion_medica', function (Blueprint $table) {
            $table->timestamp('cancelada_en')->nullable()->after('observacion_medica');
            $table->foreignId('cancelada_por')
                ->nullable()->after('cancelada_en')
                ->constrained('users')->nullOnDelete();
            $table->string('motivo_cancelacion', 500)->nullable()->after('cancelada_por');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificacion_medica', function (Blueprint $table) {
            $table->dropForeign(['cancelada_por']);
            $table->dropColumn([
                'cancelada_en', 'cancelada_por', 'motivo_cancelacion',
            ]);
        });
    }
};
