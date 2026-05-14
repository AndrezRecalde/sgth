<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('planes_bienestar', function (Blueprint $table) {
            $table->id();
            $table->integer('anio')->unique();
            $table->decimal('presupuesto', 12, 2)->default(0);
            $table->string('estado', 50)->default('planificado'); // planificado, en_ejecucion, finalizado
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('planes_bienestar'); }
};