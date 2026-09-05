<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un certificado médico emitido por error no tenía vuelta atrás.
 *
 * Y no es un papel cualquiera: cuando el paciente es el servidor titular, el
 * certificado crea además un permiso ACTIVO en Asistencia. La ruta de anulación
 * de Asistencia solo acepta permisos PENDIENTE, así que ese permiso tampoco
 * podía retirarse por su lado: quedaban los dos, para siempre.
 *
 * Se anula marcando, como el resto del módulo: quién, cuándo y por qué, con la
 * fila intacta. Es un documento clínico y su rastro importa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificados_medicos', function (Blueprint $table) {
            $table->timestamp('anulado_en')->nullable()->after('tipo_paciente');
            $table->foreignId('anulado_por')
                  ->nullable()->after('anulado_en')
                  ->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion')->nullable()->after('anulado_por');
        });
    }

    public function down(): void
    {
        Schema::table('certificados_medicos', function (Blueprint $table) {
            $table->dropForeign(['anulado_por']);
            $table->dropColumn([
                'anulado_en', 'anulado_por', 'motivo_anulacion',
            ]);
        });
    }
};
