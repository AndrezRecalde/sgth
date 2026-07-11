<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_postulante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulante_id')
                  ->constrained('postulantes')
                  ->cascadeOnDelete();
            $table->string('tipo', 100);
            $table->string('nombre_archivo', 255);
            $table->string('ruta', 500);
            $table->string('extension', 10)->nullable();
            $table->unsignedBigInteger('tamano_bytes')->nullable();
            $table->timestamps();

            $table->index('postulante_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_postulante');
    }
};
