<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('inscripciones_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->string('estado', 50)->default('preinscrito'); // preinscrito, aprobado, reprobado, abandonado
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['curso_id', 'servidor_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('inscripciones_curso'); }
};