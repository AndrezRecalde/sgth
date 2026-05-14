<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('slas', function (Blueprint $table) {
            $table->id();
            $table->string('prioridad', 50)->unique(); // critica, alta, media, baja
            $table->integer('tiempo_resolucion_horas');
            $table->integer('tiempo_respuesta_horas')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('slas'); }
};