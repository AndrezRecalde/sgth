<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_capacitacion_id')->constrained('planes_capacitacion')->restrictOnDelete();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('modalidad', 50); // presencial, virtual, hibrido
            $table->string('estado', 50)->default('planificado'); // planificado, en_ejecucion, finalizado, cancelado
            $table->decimal('costo_por_participante', 10, 2)->default(0);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('proveedor', 150)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('cursos'); }
};