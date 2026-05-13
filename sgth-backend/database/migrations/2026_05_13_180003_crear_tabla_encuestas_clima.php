<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('encuestas_clima', function (Blueprint $table) {
            $table->id();
            $table->integer('anio');
            $table->string('titulo', 150);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 50)->default('activa'); // activa, cerrada
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('encuestas_clima'); }
};