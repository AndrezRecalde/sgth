<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterios_evaluacion', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('evaluacion_id')->constrained('evaluaciones_desempeno')->onDelete('cascade');
            
            $table->string('nombre');
            $table->text('descripcion');
            $table->decimal('peso_porcentual', 5, 2); // Hasta 100.00
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criterios_evaluacion');
    }
};
