<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La desvinculación (diseño de Acciones de Personal, 9.9; fase 7.4a): lo que
 * pasa después de que una cesación o una terminación cierra el vínculo y la
 * persona no queda con otro. Hasta aquí el motor cerraba el contrato y las
 * subrogaciones, y nada más: los bienes, el usuario, el certificado y la
 * declaración de fin de gestión quedaban a la memoria de cada uno.
 *
 * Los pasos que otro módulo ya registra —los bienes del Inventario TI, el
 * usuario, el certificado laboral, la declaración patrimonial— no se marcan:
 * se leen. Aquí queda lo que Talento Humano decide: lo que no aplica, con su
 * motivo, y los pasos que todavía no tienen cálculo (la liquidación y la
 * indemnización, en la 7.4b).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desvinculaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores');
            $table->foreignId('contrato_id')->constrained('contratos_servidor');
            // El acto que cerró el vínculo: una desvinculación por cierre.
            $table->foreignId('movimiento_cierre_id')->unique()->constrained('movimientos_personal');
            $table->date('fecha_salida');
            $table->string('estado', 20)->default('abierta');
            $table->timestamp('cerrada_en')->nullable();
            $table->timestamp('cancelada_en')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            $table->timestamps();

            $table->index('estado');
        });

        DB::statement("ALTER TABLE desvinculaciones ADD CONSTRAINT desvinculaciones_estado_check
            CHECK (estado IN ('abierta', 'cerrada', 'cancelada'))");

        Schema::create('desvinculacion_pasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('desvinculacion_id')->constrained('desvinculaciones')->cascadeOnDelete();
            $table->string('paso', 30);
            $table->string('estado', 20)->default('pendiente');
            $table->text('nota')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registrado_en')->nullable();
            $table->timestamps();

            $table->unique(['desvinculacion_id', 'paso']);
        });

        DB::statement("ALTER TABLE desvinculacion_pasos ADD CONSTRAINT desvinculacion_pasos_estado_check
            CHECK (estado IN ('pendiente', 'hecho', 'no_aplica'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('desvinculacion_pasos');
        Schema::dropIfExists('desvinculaciones');
    }
};
