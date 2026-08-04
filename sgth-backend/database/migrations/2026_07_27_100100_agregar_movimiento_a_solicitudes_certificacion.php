<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula una solicitud de certificación médica con la acción de personal que
 * la originó (o que la consume). Sin esto, el guard que impide registrar una
 * acción con 'requiere_dictamen_medico' sin dictamen tendría que adivinar cuál
 * de las solicitudes del servidor le corresponde.
 *
 * En el flujo de reclutamiento la solicitud nace antes que el movimiento
 * (SolicitudCertificacionController::confirmarIncorporacion la enlaza después);
 * en el resto de acciones el movimiento nace primero y la solicitud se crea al
 * suscribirse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_certificacion_medica', function (Blueprint $table) {
            $table->foreignId('movimiento_personal_id')
                  ->nullable()
                  ->after('convocatoria_id')
                  ->constrained('movimientos_personal')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificacion_medica', function (Blueprint $table) {
            $table->dropConstrainedForeignId('movimiento_personal_id');
        });
    }
};
