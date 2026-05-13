<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accidentes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->date('fecha_accidente');
            $table->time('hora_accidente');
            $table->string('lugar_accidente');
            $table->text('descripcion_hechos');
            $table->string('gravedad', 50);
            $table->boolean('requirio_atencion_medica')->default(false);
            $table->integer('dias_reposo_medico')->default(0);
            $table->text('causa_raiz')->nullable();
            $table->text('medidas_correctivas')->nullable();
            
            $table->boolean('estado')->default(true);
            $table->foreignId('investigado_por')->nullable()->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('servidor_id');
            $table->index('fecha_accidente');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accidentes_trabajo');
    }
};
