<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('autorizaciones_vuelo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transporte_viatico_id')->constrained('transportes_viatico')->cascadeOnDelete();
            $table->foreignId('viatico_id')->constrained('viaticos');
            $table->string('documento_invitacion_ruta')->nullable();
            $table->text('justificacion')->nullable();
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->text('observacion_aprobador')->nullable();
            $table->timestamp('aprobado_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('autorizaciones_vuelo');
    }
};
