<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_medicinas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre');
            $table->string('principio_activo');
            $table->string('presentacion', 100);
            $table->string('concentracion')->nullable();
            $table->integer('stock_actual')->default(0);
            $table->integer('stock_minimo')->default(0);
            $table->date('fecha_caducidad')->nullable();
            $table->string('lote')->nullable();
            
            $table->boolean('estado')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_medicinas');
    }
};
