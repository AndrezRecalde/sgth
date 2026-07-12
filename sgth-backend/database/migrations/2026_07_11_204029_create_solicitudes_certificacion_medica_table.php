<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_certificacion_medica', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_evento', [
                'ingreso', 'reintegro', 'periodica',
                'retiro', 'especial',
            ]);
            $table->enum('origen', [
                'reclutamiento', 'expediente', 'automatico',
            ])->default('reclutamiento');
            $table->foreignId('servidor_id')
                  ->nullable()
                  ->constrained('servidores')
                  ->nullOnDelete();
            $table->foreignId('postulante_id')
                  ->nullable()
                  ->constrained('postulantes')
                  ->nullOnDelete();
            $table->foreignId('convocatoria_id')
                  ->nullable()
                  ->constrained('convocatorias')
                  ->nullOnDelete();
            $table->string('cedula_paciente', 20);
            $table->string('nombres_paciente', 300);
            $table->string('correo_paciente', 150)->nullable();
            $table->string('puesto_solicitado', 200)->nullable();
            $table->foreignId('solicitado_por')
                  ->constrained('users');
            $table->enum('estado', [
                'pendiente', 'en_proceso',
                'completada', 'cancelada',
            ])->default('pendiente');
            $table->date('fecha_limite')->nullable();
            $table->foreignId('ficha_femo_id')
                  ->nullable()
                  ->constrained('fichas_salud_ocupacional')
                  ->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['estado', 'tipo_evento']);
            $table->index('cedula_paciente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_certificacion_medica');
    }
};
