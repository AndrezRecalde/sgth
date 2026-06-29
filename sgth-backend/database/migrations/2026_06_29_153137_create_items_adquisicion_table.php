<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_adquisicion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adquisicion_id')
                  ->constrained('adquisiciones_medicamentos')
                  ->cascadeOnDelete();
            $table->foreignId('inventario_medicina_id')
                  ->constrained('inventario_medicinas');
            $table->integer('cantidad');
            $table->string('lote')->nullable();
            $table->date('fecha_caducidad')->nullable();
            $table->decimal('precio_unitario', 10, 2)->nullable();
            $table->timestamps();

            $table->index('adquisicion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items_adquisicion');
    }
};
