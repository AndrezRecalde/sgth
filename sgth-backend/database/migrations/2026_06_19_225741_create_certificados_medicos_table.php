<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_medicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_medica_id')
                  ->constrained('consultas_medicas')
                  ->restrictOnDelete();
            $table->foreignId('emitido_por')
                  ->constrained('users');
            $table->unsignedTinyInteger('dias_reposo');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->foreignId('diagnostico_cie10_id')
                  ->nullable()
                  ->constrained('diagnosticos_cie10')
                  ->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->foreignId('permiso_servidor_id')
                  ->nullable()
                  ->constrained('permisos_servidor')
                  ->nullOnDelete();
            $table->string('folio', 30)->unique()->nullable();
            $table->string('tipo_paciente', 20);
            // 'servidor' | 'beneficiario'

            $table->foreignId('created_by')
                  ->nullable()->constrained('users');
            $table->foreignId('updated_by')
                  ->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('consulta_medica_id');
            $table->index('folio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_medicos');
    }
};
