<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosticos_secundarios_consulta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_medica_id')
                  ->constrained('consultas_medicas')
                  ->cascadeOnDelete();
            $table->foreignId('diagnostico_cie10_id')
                  ->constrained('diagnosticos_cie10');
            $table->timestamps();

            $table->index('consulta_medica_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosticos_secundarios_consulta');
    }
};
