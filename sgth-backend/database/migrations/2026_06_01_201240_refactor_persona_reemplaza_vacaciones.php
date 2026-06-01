<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            // Agregar FK a servidor
            $table->foreignId('persona_reemplaza_id')
                  ->nullable()
                  ->after('persona_reemplaza')
                  ->constrained('servidores')
                  ->nullOnDelete();

            // Agregar referencia al período
            $table->foreignId('periodo_vacacion_id')
                  ->nullable()
                  ->after('persona_reemplaza_id')
                  ->constrained('periodos_vacaciones')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropForeign(['persona_reemplaza_id']);
            $table->dropForeign(['periodo_vacacion_id']);
            $table->dropColumn(['persona_reemplaza_id', 'periodo_vacacion_id']);
        });
    }
};
