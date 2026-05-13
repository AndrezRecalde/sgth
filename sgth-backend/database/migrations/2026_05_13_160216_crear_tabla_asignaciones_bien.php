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
        Schema::create('asignaciones_bien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_informatico_id')->constrained('bienes_informaticos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->date('fecha_asignacion');
            $table->date('fecha_devolucion')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('url_acta_pdf')->nullable(); // PDF de acta de entrega-recepción generado
            $table->string('estado', 50)->default('activa'); // activa, devuelta
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('bien_informatico_id');
            $table->index('servidor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones_bien');
    }
};
