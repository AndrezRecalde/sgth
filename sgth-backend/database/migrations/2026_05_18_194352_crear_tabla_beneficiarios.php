<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            
            $table->string('nombre');
            $table->string('apellido');
            $table->date('fecha_nacimiento')->nullable();
            
            $table->enum('genero', ['masculino', 'femenino', 'otro'])->nullable();
            $table->string('cedula', 10)->nullable();
            $table->enum('tipo_familiar', ['conyuge', 'hijo', 'otro'])->nullable();
            
            $table->boolean('estado')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('servidor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiarios');
    }
};
