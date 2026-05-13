<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('actividades_bienestar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_bienestar_id')->constrained('planes_bienestar')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 50)->default('planificada'); // planificada, ejecutada, cancelada
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('actividades_bienestar'); }
};