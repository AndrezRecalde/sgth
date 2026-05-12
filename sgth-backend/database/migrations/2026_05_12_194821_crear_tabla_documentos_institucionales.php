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
        Schema::create('documentos_institucionales', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('tramite_id')->constrained('tramites')->onDelete('restrict');
            
            $table->string('nombre');
            $table->string('ruta_archivo');
            $table->string('tipo_mime', 100);
            
            // Restringe la visualización exclusivamente a la UATH o a personal médico si es clínico
            $table->boolean('es_confidencial')->default(false);
            
            // Indica si debe ir al portal público de transparencia (LOTAIP Art 7)
            $table->boolean('publicar_lotaip')->default(false);
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('tramite_id');
            $table->index('es_confidencial');
            $table->index('publicar_lotaip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_institucionales');
    }
};
