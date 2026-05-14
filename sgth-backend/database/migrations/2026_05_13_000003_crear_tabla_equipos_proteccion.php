<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos_proteccion', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre');
            $table->string('tipo', 100);
            $table->string('norma_tecnica')->nullable();
            $table->integer('vida_util_meses')->nullable();
            
            $table->boolean('estado')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos_proteccion');
    }
};
