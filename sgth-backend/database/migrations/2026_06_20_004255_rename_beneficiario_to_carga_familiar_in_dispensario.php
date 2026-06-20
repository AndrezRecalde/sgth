<?php

use App\Models\Expediente\CargaFamiliar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // historias_clinicas
        Schema::table('historias_clinicas', function (Blueprint $table) {
            $table->foreignId('carga_familiar_id')
                  ->nullable()
                  ->after('beneficiario_id')
                  ->constrained('cargas_familiares')
                  ->nullOnDelete();
        });

        // agendas_medicas
        Schema::table('agendas_medicas', function (Blueprint $table) {
            $table->foreignId('carga_familiar_id')
                  ->nullable()
                  ->after('beneficiario_id')
                  ->constrained('cargas_familiares')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('historias_clinicas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carga_familiar_id');
        });

        Schema::table('agendas_medicas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carga_familiar_id');
        });
    }
};
