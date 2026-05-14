<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('evaluaciones_capacitacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones_curso')->cascadeOnDelete();
            $table->integer('nivel'); // 1: reaccion, 2: aprendizaje, 3: transferencia, 4: impacto
            $table->decimal('calificacion', 5, 2);
            $table->text('observaciones')->nullable();
            $table->foreignId('evaluador_id')->nullable()->constrained('users'); // Jefe, RRHH o null si es auto
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('evaluaciones_capacitacion'); }
};