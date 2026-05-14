<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('resultados_clima', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encuesta_id')->constrained('encuestas_clima')->cascadeOnDelete();
            
            // Usamos un unsignedBigInteger sin foreign() estricto en caso de que 
            // la tabla de unidades administrativas se llame distinto, o lo 
            // vinculamos a departaments si existe. Para este módulo, asume la existencia
            // del id de la unidad para tabular resultados agregados.
            $table->unsignedBigInteger('unidad_administrativa_id'); 
            
            // OJO: NUNCA SE INCLUYE servidor_id PARA GARANTIZAR ANONIMATO
            
            // Dimensiones calificadas (del 1 al 5)
            $table->decimal('liderazgo', 5, 2);
            $table->decimal('comunicacion', 5, 2);
            $table->decimal('trabajo_en_equipo', 5, 2);
            $table->decimal('condiciones_trabajo', 5, 2);
            $table->decimal('desarrollo_profesional', 5, 2);
            $table->decimal('reconocimiento', 5, 2);
            $table->decimal('satisfaccion_general', 5, 2);
            
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('resultados_clima'); }
};