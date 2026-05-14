<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('base_conocimiento', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('contenido');
            $table->string('categoria', 50);
            $table->string('etiquetas')->nullable();
            $table->foreignId('autor_id')->constrained('users');
            $table->integer('vistas')->default(0);
            $table->boolean('es_publico')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('base_conocimiento'); }
};