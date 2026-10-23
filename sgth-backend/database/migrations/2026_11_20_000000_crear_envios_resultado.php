<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los resultados que el servidor envía desde el Portal contra su orden (fase 6
 * de docs/diseno-examenes-medicos.md, 5.7).
 *
 * En tabla propia y no en `resultados_medicos`: hasta que un médico lo valida
 * (D2, D9), lo enviado no es un resultado de la historia clínica y no debe
 * salir en ninguna lista clínica. Es el mismo criterio que las solicitudes de
 * cargas familiares. Al validarlo nace el resultado y la fila apunta a él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envios_resultado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_examen_id')->constrained('ordenes_examen');
            $table->foreignId('enviado_por')->constrained('users');
            // Los exámenes de la orden que el servidor dice que cubre el
            // archivo. Es una propuesta: el médico los confirma al validar.
            $table->json('items');
            $table->date('fecha_resultado');
            $table->string('comentario', 300)->nullable();
            $table->string('archivo');
            $table->string('estado', 20)->default('por_validar');
            $table->string('motivo_rechazo', 500)->nullable();
            // Quién lo validó o lo rechazó, y cuándo.
            $table->foreignId('atendido_por')->nullable()->constrained('users');
            $table->timestamp('atendido_en')->nullable();
            $table->foreignId('resultado_medico_id')
                  ->nullable()
                  ->constrained('resultados_medicos')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_resultado');
    }
};
