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
        Schema::create('nominas', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7); // ej: "2026-05"
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->enum('estado', [
                'borrador',
                'en_proceso',
                'cerrada',
                'contabilizada',
                'pagada'
            ])->default('borrador');
            
            $table->decimal('total_ingresos', 12, 2)->default(0.00);
            $table->decimal('total_descuentos', 12, 2)->default(0.00);
            $table->decimal('total_neto', 12, 2)->default(0.00);
            
            $table->foreignId('cerrado_por')->nullable()->constrained('users');
            $table->timestamp('cerrado_en')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índice recomendado para búsquedas por periodo
            $table->index('periodo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nominas');
    }
};
