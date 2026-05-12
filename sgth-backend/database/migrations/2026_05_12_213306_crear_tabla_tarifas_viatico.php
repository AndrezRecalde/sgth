<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifas_viatico', function (Blueprint $table) {
            $table->id();
            
            $table->enum('zona', ['dentro_provincia', 'fuera_provincia', 'exterior']);
            $table->enum('nivel', ['autoridad', 'servidor']);
            $table->decimal('valor_diario', 8, 2);
            $table->string('pais_destino')->nullable(); // Aplica sólo si zona es exterior
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['zona', 'nivel', 'pais_destino'], 'uq_tarifa_viatico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifas_viatico');
    }
};
