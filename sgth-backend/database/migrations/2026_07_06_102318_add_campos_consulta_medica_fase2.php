<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendas_medicas', function (Blueprint $table) {
            $table->timestamp('marcado_no_presentado_en')
                  ->nullable()->after('registrado_en');
            $table->foreignId('marcado_no_presentado_por')
                  ->nullable()->after('marcado_no_presentado_en')
                  ->constrained('users');
            $table->timestamp('reactivado_en')
                  ->nullable()->after('marcado_no_presentado_por');
            $table->foreignId('reactivado_por')
                  ->nullable()->after('reactivado_en')
                  ->constrained('users');
        });

        Schema::table('consultas_medicas', function (Blueprint $table) {
            $table->foreignId('agenda_medica_id')
                  ->nullable()->after('historia_clinica_id')
                  ->constrained('agendas_medicas');
            $table->text('enfermedad_actual')
                  ->nullable()->after('motivo_consulta');
            $table->string('tipo_atencion', 30)
                  ->default('primera_vez')->after('hora_consulta');
            $table->string('tipo_diagnostico', 20)
                  ->default('presuntivo')->after('tipo_atencion');
        });
    }

    public function down(): void
    {
        Schema::table('agendas_medicas', function (Blueprint $table) {
            $table->dropForeign(['marcado_no_presentado_por']);
            $table->dropForeign(['reactivado_por']);
            $table->dropColumn([
                'marcado_no_presentado_en',
                'marcado_no_presentado_por',
                'reactivado_en',
                'reactivado_por',
            ]);
        });

        Schema::table('consultas_medicas', function (Blueprint $table) {
            $table->dropForeign(['agenda_medica_id']);
            $table->dropColumn([
                'agenda_medica_id',
                'enfermedad_actual',
                'tipo_atencion',
                'tipo_diagnostico',
            ]);
        });
    }
};
