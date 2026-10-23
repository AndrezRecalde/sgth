<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La revisión del médico sobre cada resultado (fase 4 de
 * docs/diseno-examenes-medicos.md, D3): normal, alterado o crítico, con un
 * comentario que se guarda cifrado como las notas de la consulta.
 *
 * Lo nuevo nace «por revisar». Lo que ya estaba subido se marca como revisado,
 * sin interpretación: nadie lo va a leer de nuevo para clasificarlo, y si
 * entrara como pendiente inundaría la bandeja el día del despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resultados_medicos', function (Blueprint $table) {
            $table->string('estado_revision', 20)->default('por_revisar')->after('fecha_resultado');
            $table->string('interpretacion', 20)->nullable()->after('estado_revision');
            // Cifrado en el modelo: text, porque el cifrado alarga el valor.
            $table->text('comentario_medico')->nullable()->after('interpretacion');
            $table->foreignId('revisado_por')->nullable()->after('comentario_medico')->constrained('users');
            $table->timestamp('revisado_en')->nullable()->after('revisado_por');
            // Un crítico queda fijo en «Mi jornada» de quien pidió el examen
            // hasta que lo ve (D3); si lo revisó él mismo, ya lo vio.
            $table->timestamp('critico_visto_en')->nullable()->after('revisado_en');

            $table->index('estado_revision');
        });

        DB::table('resultados_medicos')->update(['estado_revision' => 'revisado']);
    }

    public function down(): void
    {
        Schema::table('resultados_medicos', function (Blueprint $table) {
            $table->dropIndex(['estado_revision']);
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropColumn(['estado_revision', 'interpretacion', 'comentario_medico', 'revisado_en', 'critico_visto_en']);
        });
    }
};
