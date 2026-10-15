<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La credencial de los servidores y sus impresiones
 * (docs/diseno-comunicacion-social.md, 6 y 7.2; fase 3.1).
 *
 * La credencial nace sola cuando un servidor con vínculo vigente tiene foto;
 * Comunicación la imprime, la revoca y recibe la tarjeta cuando el servidor
 * sale. Cada impresión guarda lo que se imprimió, para saber cuándo una
 * tarjeta quedó desactualizada.
 *
 * No se crean credenciales aquí: las crea `sgth:credenciales:conciliar`, que
 * corre cada noche y se puede lanzar a mano después de migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credenciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            // Aleatorio y largo: es lo que lleva el QR y lo que se verifica sin sesión.
            $table->string('codigo', 64)->unique();
            $table->string('estado', 20);
            // La fecha de fin del contrato; null en nombramientos y contratos indefinidos.
            $table->date('valida_hasta')->nullable();
            // La revocada a la que esta repone.
            $table->foreignId('reemplaza_a_id')->nullable()->constrained('credenciales')->nullOnDelete();
            $table->timestamp('revocada_en')->nullable();
            $table->foreignId('revocada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_revocacion', 30)->nullable();
            $table->string('detalle_revocacion', 500)->nullable();
            $table->timestamp('anulada_en')->nullable();
            // La tarjeta impresa que el servidor entregó al salir (paso de la desvinculación).
            $table->date('devuelta_en')->nullable();
            $table->foreignId('devolucion_registrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('devolucion_nota', 500)->nullable();
            $table->timestamps();

            $table->index(['servidor_id', 'created_at']);
            $table->index('estado');
        });

        // Una sola viva —vigente o vencida— por servidor: dos sincronizaciones
        // a la vez chocarían aquí en vez de dejarle dos.
        DB::statement(
            'CREATE UNIQUE INDEX credenciales_una_viva '
            ."ON credenciales (servidor_id) WHERE estado IN ('vigente', 'vencida')"
        );

        Schema::create('impresiones_credencial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credencial_id')->constrained('credenciales')->cascadeOnDelete();
            $table->foreignId('impresa_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('formato', 10);
            // Las que salieron en la misma tanda.
            $table->uuid('lote')->nullable()->index();
            // Nombre, cargo, unidad, cédula, tipo de sangre, contacto de
            // emergencia, válida hasta y la foto, como quedaron en la tarjeta.
            $table->json('datos_impresos');
            $table->timestamp('created_at')->nullable();

            $table->index(['credencial_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impresiones_credencial');
        Schema::dropIfExists('credenciales');
    }
};
