<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puesto_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puesto_id')
                  ->constrained('puestos')
                  ->cascadeOnDelete();
            $table->string('descripcion', 200);
            $table->unsignedTinyInteger('orden')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('puesto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puesto_actividades');
    }
};
