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
        Schema::create('destinos_viatico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viatico_id')->constrained('viaticos')->cascadeOnDelete();
            $table->enum('tipo_destino', ['nacional', 'internacional']);
            $table->foreignId('provincia_id')->nullable()->constrained('provincias');
            $table->foreignId('ciudad_id')->nullable()->constrained('ciudades');
            $table->string('pais')->nullable();
            $table->string('estado_region')->nullable();
            $table->date('fecha_llegada');
            $table->date('fecha_salida');
            $table->unsignedTinyInteger('orden')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destinos_viatico');
    }
};
