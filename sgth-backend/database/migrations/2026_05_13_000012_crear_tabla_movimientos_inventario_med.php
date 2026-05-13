<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario_med', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_medicina_id')->constrained('inventario_medicinas')->restrictOnDelete();
            $table->string('tipo_movimiento', 50);
            $table->integer('cantidad');
            $table->integer('stock_resultante');
            $table->text('motivo');
            
            $table->foreignId('referencia_receta_id')->nullable()->constrained('recetas_medicas')->nullOnDelete();
            $table->foreignId('registrado_por')->constrained('users');
            
            $table->timestamps();
            // SIN softDeletes por requerimiento (Kardex Inmutable)
            
            $table->index('inventario_medicina_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario_med');
    }
};
