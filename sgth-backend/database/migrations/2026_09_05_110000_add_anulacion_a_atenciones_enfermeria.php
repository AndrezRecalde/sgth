<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una atención de enfermería registrada no se podía tocar: ni corregir ni
 * anular. Una inyección apuntada al paciente equivocado se quedaba ahí para
 * siempre, y la única salida era editar la base a mano.
 *
 * Se anula como el resto del módulo —marcando, no borrando—: quién, cuándo y
 * por qué, con la fila intacta. Es un registro clínico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('atenciones_enfermeria', function (Blueprint $table) {
            $table->timestamp('anulado_en')->nullable()->after('atendido_en');
            $table->foreignId('anulado_por')
                  ->nullable()->after('anulado_en')
                  ->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion')->nullable()->after('anulado_por');
        });
    }

    public function down(): void
    {
        Schema::table('atenciones_enfermeria', function (Blueprint $table) {
            $table->dropForeign(['anulado_por']);
            $table->dropColumn([
                'anulado_en', 'anulado_por', 'motivo_anulacion',
            ]);
        });
    }
};
