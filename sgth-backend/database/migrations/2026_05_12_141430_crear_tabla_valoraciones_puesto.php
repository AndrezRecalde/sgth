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
        Schema::create('valoraciones_puesto', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('puesto_id')
                  ->constrained('puestos')
                  ->cascadeOnDelete();
                  
            $table->string('factor');
            $table->decimal('puntos', 8, 2);
            $table->text('observacion')->nullable();
            
            $table->foreignId('valorado_por')
                  ->constrained('users')
                  ->restrictOnDelete();
                  
            $table->timestamp('valorado_en');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('valoraciones_puesto');
    }
};
