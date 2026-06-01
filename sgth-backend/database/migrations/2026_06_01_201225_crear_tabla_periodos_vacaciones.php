<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_vacaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('servidor_id')
                  ->constrained('servidores')
                  ->onDelete('cascade');

            $table->integer('anio');

            $table->date('fecha_inicio_periodo');
            $table->date('fecha_fin_periodo');

            // Régimen aplicable
            $table->enum('regimen', ['losep', 'codigo_trabajo']);

            // Antigüedad al inicio del período (en años completos)
            $table->integer('anios_antiguedad')->default(0);

            // Días generados según escala
            $table->decimal('dias_generados', 8, 2)->default(0);

            // Días utilizados (suma de vacaciones aprobadas)
            $table->decimal('dias_utilizados', 8, 2)->default(0);

            // Días saldo = generados - utilizados
            $table->decimal('dias_saldo', 8, 2)->default(0);

            // Saldo acumulado total (incluyendo períodos anteriores)
            $table->decimal('saldo_acumulado', 8, 2)->default(0);

            $table->enum('estado', [
                'abierto',
                'cerrado',
                'vencido',
            ])->default('abierto');

            // Alerta enviada cuando se acerca al límite
            $table->boolean('alerta_enviada')->default(false);

            $table->timestamps();

            // Un servidor solo tiene un período por año
            $table->unique(['servidor_id', 'anio']);

            // Índices para consultas frecuentes
            $table->index(['servidor_id', 'estado']);
            $table->index(['anio', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos_vacaciones');
    }
};
