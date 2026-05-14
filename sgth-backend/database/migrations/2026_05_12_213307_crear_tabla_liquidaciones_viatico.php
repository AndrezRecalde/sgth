<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidaciones_viatico', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('viatico_id')->unique()->constrained('viaticos')->onDelete('cascade');
            
            $table->json('facturas')->nullable(); // Array de facturas justificativas
            $table->decimal('total_facturas', 8, 2)->default(0.00);
            $table->decimal('monto_justificado', 8, 2)->default(0.00);
            $table->decimal('diferencia_devolver', 8, 2)->default(0.00);
            
            $table->date('fecha_retorno')->nullable(); // Para calcular los 5 días hábiles
            $table->date('fecha_liquidacion')->nullable();
            
            $table->text('observaciones')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones_viatico');
    }
};
