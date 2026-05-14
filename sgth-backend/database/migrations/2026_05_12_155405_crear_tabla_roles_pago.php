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
        Schema::create('roles_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomina_id')->constrained('nominas')->onDelete('cascade');
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('cascade');
            
            $table->decimal('total_ingresos', 12, 2)->default(0.00);
            $table->decimal('total_descuentos', 12, 2)->default(0.00);
            $table->decimal('total_neto', 12, 2)->default(0.00);
            
            $table->boolean('enviado_por_correo')->default(false);
            $table->timestamp('enviado_en')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Garantizar que no se dupliquen roles para el mismo servidor en una misma nómina
            $table->unique(['nomina_id', 'servidor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles_pago');
    }
};
