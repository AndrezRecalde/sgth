<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feriados_institucionales', function (Blueprint $table) {
            $table->id();
            
            $table->date('fecha')->unique();
            $table->string('descripcion');
            $table->boolean('es_nacional')->default(true); // false = feriado local del GAD
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados_institucionales');
    }
};
