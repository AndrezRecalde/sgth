<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historias_clinicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            
            // Campos de texto largo preparados para cifrado en el modelo
            $table->text('antecedentes_personales')->nullable();
            $table->text('antecedentes_familiares')->nullable();
            $table->text('alergias')->nullable();
            $table->text('medicacion_habitual')->nullable();
            
            $table->string('grupo_sanguineo', 5)->nullable();
            
            $table->boolean('estado')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('servidor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historias_clinicas');
    }
};
